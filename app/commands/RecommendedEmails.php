<?php

use Core\Repositories\ElasticSearch\EsJobRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class RecommendedEmails extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'RecommendedEmails';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Send Recommended Jobs emails to all jobs';

    /**
     * Mandrill template name.
     *
     * @var string
     */
    protected $template_name = 'recommendation-jobs';

    /**
     * Email Subject.
     *
     * @var string
     */
    protected $subject = 'New Recommended Jobs available for you';

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
     * get all jobs within this period.
     *
     * @var string
     */
    protected $period_from = '2M';

	/**
     * Turn Test mode ON or OFF.
     *
     * @var string
     */
    protected $test_mode 	 = true;

	/**
	 * The test email that will recieve this email.
	 *
	 * @var string
	 */
	protected $test_email  	 = 'ahmed.mamdouh@jobzella.com';

	/**
	 * The test user username who is the owner of the recommended jobs.
	 *
	 * @var string
	 */
	protected $test_username = 'ahmed.mamamama';

	/**
	 * Essential variables.
	 */
	protected $n = 0;
	protected $es_jobs_repo;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->es_jobs_repo = new EsJobRepository();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		DB::disableQueryLog();	
		$this->getPeople();

		/*
		 * ===========================
		 * PUSH NOTIFICATION CODE
		 * To Be Used
		 * ===========================
<<<<<<< Updated upstream
		 * $message = PushNotification::Message( 'New Recommended Jobs available for you' ,array(
			   'custom' => [
				   'type' => 'recommended-jobs'
			   ]
		   ));

		$message = PushNotification::Message('sdfsfdsf' ,array(
			'custom' => []
		));
=======



			$collection = PushNotification::app('iphone')
				->to("e144c20f37dc62e8f384f4a630027dc4824fbb2f9c7109da56dc601d2109afc9")
				->send($message);*/

	}

	public function getPeople()
	{
		DB::disableQueryLog();

		if($this->test_mode)
		{
			$user = DB::table('users')
				->join('resumes', 'resumes.member_id', '=', 'users.id')
				->where('users.username','=',$this->test_username)
				->first();
			$this->sendRecommendedJobsTo($user);
		}else{
			DB::table('users')
				->join('resumes', 'resumes.member_id', '=', 'users.id')
				->where('users.username','not like','%admin%')
				->whereIN('users.status',array(0,1))
				->orderBy('resumes.created_at', 'desc')
				->chunk(5000, function($people)
				{
					foreach ($people as $user)
					{
						$this->sendRecommendedJobsTo($user);
					}
				});
		}
	}

	public function sendRecommendedJobsTo($user)
	{
		if(!empty($user->job_title))
		{

			$recommended = $this->es_jobs_repo->getByRecommendedJobs($user->job_title,5,0,$user->member_id,$this->period_from);
			if(@!empty($recommended['hits']['hits']))
			{
				$this->n++;
				$jobs = [];
				$recommended = $recommended['hits']['hits'];
				foreach ($recommended as $job)
				{
					$job = $job['_source'];
					if(isset($job['job_portal_id']))
					{
						$job['confidential_company'] = $job['confidential'];
						$job['logo'] = $job['employer_logo'];
					}
					$jobs[] = (object) $job;
				}
				$recommended_jobs = View::make('emails.recommended_jobs', array('jobs' => $jobs))->render();
				$this->addEmailRecoded($user, $recommended_jobs);
				$this->info('Sending to ' . $user->first_name . ' ' . $user->last_name . ' => DONE | Total Sent => ' . $this->n);
			}else{
				$this->line('Sending to ' . $user->first_name . ' ' . $user->last_name . ' => Skipped (no recommended)');
			}
		}else{
			$this->line('Sending to ' . $user->first_name . ' ' . $user->last_name . ' => Skipped (empty job title)');
		}
	}

	public function addEmailRecoded( $user, $recommended_jobs )
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

		$data = array(
			'tname' => $this->template_name,
			'vars' => json_encode([
				[
					'name' => 'jobs',
					'content' => $recommended_jobs
				]
			]),
			'email' => $email,
			'subject' => str_replace('*|first_name|*',$first_name, $this->subject),
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
