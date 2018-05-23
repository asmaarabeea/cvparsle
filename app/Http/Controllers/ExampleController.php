<?php

namespace App\Http\Controllers;
use DB;
use App;
use Illuminate\Http\Request;
use Carbon\Carbon;
// use App\Http\Controllers\AccountRepository;

class ExampleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function index()
    {
        return view('index'); 
    }

    public function getUsers(Request $request)
    {
        // DB::connection('mysql3')->select('users');
        $db_conn = DB::connection('mysql3');
        $user = $db_conn->table('users')
        ->where('name' , 'like' , 'asmaa rabeea')->first();
        // dd($user);

        $email = 'asmaa.rabeea7@gmail.com';
        $password = strstr($email, '@', TRUE);
        $name = "Asmaa Rabeea Elabd";

        $account = $db_conn->table('accounts')->insertGetId([
                'email'   => $email,
                'account_type_id' => 1,
                'slug'  => $this->generateSlug($name),
                'password'    =>app('hash')->make($password),
                'mobile'      => "010901410293",
                'verified'     => 1,
                'city'        => 'Ain Shams' ,
                'address'  =>  'Ain Shams , cairo', 
                'address'  => 'Ain Shams , cairo',
                'created_at'     => Carbon::now(),
            ]);

        dd($account);
    }

    public function generateSlug($name)
    {
        $db_conn = DB::connection('mysql3');

        $name = str_slug($name, '-');
        if (!$db_conn->table('accounts')->where('slug',$name)->first()) {
            return $name;
        } else {
            return $this->generateSlug($name . str_random($length = 5));
        }
    }

    public function parse(Request $request)
    {
        // ini_set('default_socket_timeout', 600);
        $cvs = $request->file('cvs');

        $output = "";

        if ($request->hasFile('cvs')) {

            set_time_limit(0);
            ob_implicit_flush(true);
            ob_end_flush();
            // ob_start();
            foreach ($cvs as $key => $cv) {

                $output = \CV::parse($cv);

                $response = $cv->getClientOriginalName();
                // echo "<br> Line to show.".$response."\n";  
                $response = array('message' => $response . ' complete', 'progress' => 200);

                $educations = []; $educationss = "";
                foreach ($output['education'] as $education) {
                    $educations[] = $education['degree_name'] ? $education['degree_name'] : $education['organization_unit'] ;
                    $educationss = implode(" , ",$educations);
                }

                echo json_encode($output['contact_info']);

                $db_conn = DB::connection('mysql3');

                if (!empty($output['contact_info']['email']) || $db_conn) {

                    if ( !$db_conn->table('accounts')->where('email' , $output['contact_info']['email'])->first()) {

                        $password = strstr($output['contact_info']['email'] , '@' , TRUE);

                        // $account = $db_conn->table('accounts')->insertGetId([
                        //     'email'       => $output['contact_info']['email'],
                        //     'slug'        => $this->generateSlug($output['contact_info']['full_name']),
                        //     'account_type_id' => 1,
                        //     'password'    => app('hash')->make($password),
                        //     'mobile'      => $output['contact_info']['telephone']['formatted_number'],
                        //     'verified'    => 1,
                        //     'city'        => $output['contact_info']['city'] ,
                        //     'address'     => $output['contact_info']['city'], 
                        //     'country'     => $output['contact_info']['postal_code'],
                        //     'created_at'  => Carbon::now(),
                        // ]);

                        // $db_conn->table('users')->insert([
                        //     'name'          => $output['contact_info']['full_name'],
                        //     'phone'         => $output['contact_info']['telephone']['formatted_number'],
                        //     'birth_year'    => $output['contact_info']['birth_date'],
                        //     'gender'        => $output['contact_info']['sex'] ? $output['contact_info']['sex'] : "male",
                        //     'social_status'  => $output['contact_info']['marital_status'] , 
                        //     'education_level'=> $educationss,
                        //     'job_level'      => $output['experiences'][0]['job_title'],
                        //     'account_id'     => $account,
                        //     'created_at'     => Carbon::now(),
                        // ]);

                    }
                }
                
                sleep(4);

            }

            $response = array('message' => 'Complete', 'progress' => 100);
            echo json_encode($response);
            // echo "Done.";
            // ob_end_flush();
    
         // return response()->json($output, 200);

        }

    }

   
}
