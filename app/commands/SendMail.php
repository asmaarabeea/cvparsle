<?php
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
class SendMail extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SendMail';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';
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
        $this->mandrill();
        //$this->syncLog();
    }

    public function mandrill()
    {
        $data = Mandrilldb::where('status', '=', 0)->take(1000)->orderBy('priority','desc')->get()->toArray();
     
        $ids = array();
        if (!empty($data)) {
            foreach ($data as $value) {
                array_push($ids, $value['id']);
            }
            Mandrilldb::whereIn('id', $ids)->update(
                array(
                    'status' => -1
                )
            );
            foreach ($data as $to) {
                $to['vars'] = trim(preg_replace('/\s\s+/', ' ', $to['vars']));
                $status = mandril::send_mail($to['tname'], json_decode($to['vars']), $to['email'], $to['subject'],$to['from'],$to['from_name']);
                $affectedRows = Mandrilldb::where('id', '=', $to['id'])->update(
                    array(
                        'status' => $status
                    )
                );
                echo $affectedRows;
            }
        }
    }
}
?>