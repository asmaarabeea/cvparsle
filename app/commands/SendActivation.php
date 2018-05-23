<?php
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class SendActivation extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'SendActivation';

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
        $this->activate();
    }

  public function activate()
    {
            $data = Member::where('activated', '=', 0)->get(array('id'));

        foreach($data as $value)
            {
                mandril::resend_activation_mail($value->id);
            }
    }

}
?>