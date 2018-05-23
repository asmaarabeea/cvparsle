<?php
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class VerifyUsers extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'VerifyUsers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'VerifyUsers.';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $this->activate();
    }

    public function activate()
    {
        Member::where('status', '=', 0)->where('membership_type', '=', 1)->select(array('id','email','first_name'))->chunk(
            5000,
            function ($all_ids) {
                if (!empty($all_ids)) {
                    foreach ($all_ids as $value) {

                $this->info($value->email);

            \DB::table('users')
                ->where('id', $value->id)
                ->update(array('status' => 1));

            $data = array(
                'tname' => unserialize(\Core\Enums\EmailTemplateNamesEnum::VERIFY_EMAIL),
                'vars' => json_encode(array(
                    array(
                        'name' => 'username',
                        'content' => $value->first_name
                    )
                )),
                'email' => $value->email,
                'subject' => 'Your Jobzella Account is Verified, Start Applying to Jobs Now!',
                'status' => 0
            );

                Mandrilldb::insert($data);
            }
                }
            }
        );
    }



}
?>