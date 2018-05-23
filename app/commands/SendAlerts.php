<?php

use Core\Managers\ElasticSearch\ESCourseManager;
use Core\Managers\ElasticSearch\ESJobManager;
use Core\Repositories\AlertsRepository;
use Core\Repositories\ElasticSearch\EsCourseRepository;
use Core\Repositories\ElasticSearch\EsJobRepository;
use Core\Repositories\UserRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SendAlerts extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'SendAlerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send emails include new jobs & courses depending on every user saved search';

    /**
     * Mandrill template name.
     *
     * @var string
     */
    protected $template_name = [];
//    protected $template_name = 'alert-emails';

    /**
     * Email Subject.
     *
     * @var string
     */
    protected $subject = 'New *|alert_title|* *|alert_type|* available for you.';

    /**
     * Sender Email address.
     *
     * @var string
     */
    protected $from = 'no-reply@jobzella.com';

    /**
     * Sender name.
     *
     * @var string
     */
    protected $from_name = 'Jobzella';

    /**
     * Turn Test mode ON or OFF.
     *
     * @var string
     */
    protected $test_mode 	 = false;

    /**
     * The test email that will recieve this email.
     *
     * @var string
     */
    protected $test_email  	 = 'mena.nagy@jobzella.com';

    /**
     * The test user username who is the owner of the recommended jobs.
     *
     * @var string
     */
    protected $test_username = 'ahmedlaravel-261';

    /**
     * The test user saved alert type.
     * 1 = jobs
     * 2 = courses
     *
     * @var string
     */
    protected $test_alert_type = 1;

    /**
     * List of periods
     * [id => Days Intervals]
     *
     * @var array
     */
	protected $periods = [
        "1" => "1",
        "2" => "7",
        "3" => "3",
    ];

    protected $types = [
        "1" => "jobs",
        "2" => "courses"
    ];
	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
        $this->template_name = [
            'jobs'    => unserialize(\Core\Enums\EmailTemplateNamesEnum::NEW_ALERTS_JOBS),
            'courses' => unserialize(\Core\Enums\EmailTemplateNamesEnum::NEW_ALERTS_COURSES)
        ];
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
        $alerts_repo = new AlertsRepository();

        if($this->test_mode)
        {
            $user = DB::table('users')
                ->join('resumes', 'resumes.member_id', '=', 'users.id')
                ->where('users.username','=',$this->test_username)
                ->first();

            $alerts =  $alerts_repo->listAlerts($user->member_id, $this->test_alert_type);

            if(count($alerts) > 0)
            {
                foreach($alerts as $alert)
                {
                    $user = (new UserRepository())->getAllDataById($alert->member_id);
                    $this->sendAlertsTo($alert, $user["user_data"][0]);
                }
            }else{
                $this->line("ERROR! - The Test user ({$user->first_name} {$user->last_name}) doesn't have any saved " . $this->types[$this->test_alert_type] . " alerts.");
            }

        }else
        {
            $alerts =  $alerts_repo->listAllAlerts();
            if($alerts)
            {
                foreach($alerts as $alert)
                {
                    $last_sent = new DateTime($alert->last_sent);
                    $period    = new DateInterval( 'P' . $this->periods[ $alert->email_period ] . 'D' );
                    $next_send = $last_sent->add($period)->format('Y-m-d H:i:s');
                    if( time() > strtotime($next_send) )
                    {
                        $user = (new UserRepository())->getAllDataById($alert->member_id);
                        (new AlertsRepository())->updateLastSent($alert->id);
                        $this->sendAlertsTo($alert, $user["user_data"][0]);
                    }
                }
            }
        }
	}

    public function esManager($type, $request, $last_sent)
    {
        if($type == 1)
        {
            $filters = [
                "and" => [
                    [
                        "range" => [
                            "date" => [
                                "gte" => date("Y-m-d", strtotime($last_sent))
                            ],
                        ],
                    ]
                ]
            ];
            return (new ESJobManager(new EsJobRepository(), $filters))->getJobs($request);
        }else
        {
            $filters = [
                "and" => [
                    [
                        "range" => [
                            "submit_date" => [
                                "gte" => strtotime($last_sent)
                            ],
                        ],
                    ]
                ]
            ];
            return (new ESCourseManager(new EsCourseRepository(), $filters))->getCourses($request);
        }
    }

    public function sendAlertsTo($alert, $user)
    {
        parse_str($alert->search_criteria, $filters);
        Input::merge($filters);
        $es_items = $this->esManager($alert->type, Input::all(), $alert->last_sent);

        if(@!empty($es_items['data']))
        {
            $items = [];
            foreach ($es_items['data'] as $item)
            {
                $item = $item['_source'];
                if(isset($item['job_portal_id']))
                {
                    $item['confidential_company'] = $item['confidential'];
                    $item['logo'] = $item['employer_logo'];
                }
                $items[] = (object) $item;
            }
            $items = array_slice($items, 0, 6);
            $alert_items = View::make('emails.alerts_' . $this->types[$alert->type], [ $this->types[$alert->type] => $items ])->render();
            $this->addEmailRecoded($user, $alert, $alert_items);
            $this->info('Sending ' . $this->types[$alert->type] . ' to ' . $user->first_name . ' ' . $user->last_name . ' => DONE');
        }else{
            $this->line('Sending ' . $this->types[$alert->type] . ' to ' . $user->first_name . ' ' . $user->last_name . ' => Skipped (no new ' . $this->types[$alert->type] . ' since last alert)');
        }
    }

    public function addEmailRecoded( $user, $alert, $alert_items)
    {
        DB::disableQueryLog();

        if (isset($user->email)) {
            $email = $this->test_mode ? $this->test_email : $user->email;
        } else {
            return false;
        }
        if (isset($user->first_name)) {
            $first_name = $user->first_name;
        } else {
            return false;
        }

        if (isset($user->member_id)) {
            $member_id = $user->member_id;
        } else {
            return false;
        }

        $type = $this->types[$alert->type];
        $subject = str_replace('*|alert_title|*', $alert->title, $this->subject);
        $subject = str_replace('*|alert_type|*', $type, $subject);

        $data = array(
            'tname' => $this->template_name[$type],
            'vars' => json_encode([
                [
                    'name' => $type,
                    'content' => $alert_items
                ]
            ]),
            'email' => $email,
            'subject' => str_replace('*|first_name|*',$first_name, $subject),
            'status' => 0
        );

        if (!empty($this->from)) {
            $data['from'] = $this->from;
        }

        if (!empty($this->from_name)) {
            $data['from_name'] = $this->from_name;
        }

        $data['priority'] = -50;

        if (Notification::mail_notification($member_id)) {
            Mandrilldb::insert($data);
        }

    }

}
