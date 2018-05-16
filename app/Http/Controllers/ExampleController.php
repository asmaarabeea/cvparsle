<?php

namespace App\Http\Controllers;
use DB;
use Illuminate\Http\Request;
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

                // $output = \CV::parse($cv);

                $response = $cv->getClientOriginalName();
                // echo "<br> Line to show.".$response."\n";  
                $response = array('message' => $response . ' complete', 'progress' => 200);
                echo json_encode($response);
                // ob_flush();
                // flush();
                sleep(4);
            }

          // echo "Done.";
          // ob_end_flush();
            $response = array('message' => 'Complete', 'progress' => 100);
            echo json_encode($response);
    
         // return response()->json($output, 200);

        }

    }

   
}
