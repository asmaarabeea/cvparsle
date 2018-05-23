<?php

include(app_path() . '/Core/Services/PdfToTextService.php');

use Core\Enums\EmailTemplateNamesEnum;
use Core\Lib\Upload\UploaderLocal;
use Core\Lib\Upload\UploaderS3;
use Core\Managers\ElasticSearch\ESPeopleManager;
use Core\Repositories\ElasticSearch\EsPeopleRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class CvParser extends Command
{
    /**
     * The name and signature of the console command.
     * ex: php artisan CvParser test_cv.zip attach
     * Use (attach) to allow attaching the cv file to the user profile
     * @var string
     */
    private $csv_file_name = '';
    private $industry_id = NULL;

    protected $name = 'CvParser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parsing cvs';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function getArguments()
    {
        return [
            ['path', InputArgument::REQUIRED],
            ['attach', InputArgument::OPTIONAL]
        ];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        // foreach (trans('industry') as $key => $val) {
        //     $val = str_replace('/' , '_' , $val);
        //     echo $val . "<br>";
        //     File::makeDirectory(__DIR__."/folders/$val" , $mode = 0777, true, true);
        // }
        // dd('dd');

        $path = $this->argument('path');
        if ($this->confirm('Do you wish to continue? [y|n]', 'y')) {
            $csv_file = $this->createCsvFileSheet();
            $this->parse($path, $csv_file);
        }
    }

    public function parse($path, $csv_file)
    {
        $dir  = storage_path('cvs');
        $data = $this->unzipFile($path, $dir);

        //check the unzipped file is not false
        if (!$data) {
            return $this->error('Wrong zip file!! :(');
        }

        foreach ($data as $key => $row) {
            $cv       = $row[0];
            $industry = $row[1];

            $xml          = $this->parseCvToXml($cv);
            $this->xm2Arr = $this->parseXmlToArr($xml);
            $info         = $this->getCvData();

            $percentage = ceil($key / count($data) * 100);

            $this->info(($key + 1) . '/' . count($data) . ' --' . $percentage . '% (' . $cv . ')');

            //Insert into account table
            if ($this->checkIfValidCv($info, $industry)) {

                //insert user data in accounts table
                $this->comment("inserting data into accounts table");
                list($account_id, $username, $password) = $this->insertIntoAccount($info);
                //insert user data in userstable
                $this->comment("inserting data into users table");
                $user_id = $this->insertIntoUsers($info, $account_id, $username);
                //update user_id in accounts table
                $this->comment("Update user_id in accounts table");
                $this->updateaccountsUserId($account_id, $user_id);

                //insert resume data in resumes table
                $this->comment("Inserting data into resumes table");
                $resume_id = $this->insertIntoResumes($info, $user_id, $this->industry_id);

                if ($info['school']['name'] != NULL) {
                    $this->comment("inserting data into resumesEducations table");
                    $this->insertIntoResumesEducations($info, $resume_id);
                }
                if (count($info['experiences']) > 0) {
                    $this->comment("inserting data into resumesExperiences table");
                    $this->insertIntoResumesExperiences($info, $resume_id);
                }
                if (count($info['languages']) > 0) {
                    $this->comment("inserting data into resumesLanguages table");
                    $this->insertIntoResumesLanguages($info, $resume_id);
                }
                if (count($info['skills']) > 0) {
                    $this->comment("inserting data into resumesLanguages table");
                    $this->insertIntoResumesSkills($info, $resume_id);
                }

                // attach cv file
                $attach_arg = $this->argument('attach');
                if (!empty($attach_arg)) {
                    $this->comment('Attaching CV...');
                    $cv_filename = $user_id . '_' . str_replace(' ', '_', pathinfo($row[0])['basename']);
                    $cv_path     = public_path('uploads/attachment');
                    file_put_contents($cv_path . '/' . $cv_filename, file_get_contents($row[0]));
                    (new UploaderS3(new UploaderLocal()))->uploadOrignalImage($cv_filename, $cv_path);
                    $cv_details = ['file_desc' => pathinfo($row[0])['filename'], 'mime_type' => mime_content_type($row[0])];
                    \Attach::file($user_id, 'cv', 'attachment/' . $cv_filename, $cv_details);
                }


                //extract photo from the cv
                $this->comment("Extract photo from cv");
                $this->extractImageFromCv($row[0], $resume_id);

                (new ESPeopleManager(new EsPeopleRepository()))->syncResume($resume_id);
                $this->comment('Sync to Elasticsearch');

                $email            = [];
                $email['tname']   = unserialize(EmailTemplateNamesEnum::AUTOMATIC_REGISTRATION);
                $email['vars']    = json_encode([
                    [
                        'name'    => 'name',
                        'content' => strstr($info['name'], ' ', TRUE)
                    ],
                    [
                        'name'    => 'username',
                        'content' => $username
                    ],
                    [
                        'name'    => 'password',
                        'content' => $password
                    ]
                ]);
                $email['email']   = $info['email'];
                $email['subject'] = "Welcome to Jobzella";
                Mandrilldb::insert($email);
                $this->comment('Sending Welcome mail');

                $this->comment('User_name is:' . $username);
                $this->comment('Password is:' . $password);

                $this->addUserToCsvFile($csv_file, $account_id, $user_id, $info['email'], $info['name'], $username, $password);
                $this->comment(nl2br(''));
            }
        }

        $this->downloadCsvFile($csv_file);
        \File::deleteDirectory($dir);
        $this->info("Files has been deleted successfully");
        $this->info("Done!!");

    }

    public function unzipFile($file, $dir)
    {
        $zip = new \ZipArchive;
        $res = $zip->open($file);

        if ($res === TRUE) {
            $zip->extractTo($dir);
            $zip->close();
        } else {
            return FALSE;
        }

        //list all folders inside the unzipped folder
        $industries = array_diff(scandir($dir), ['..', '.']);
        $cvs_arr    = [];
        foreach ($industries as $directory) {

            $path = $dir . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }

            $cvs = array_diff(scandir($path), ['..', '.']);
            foreach ($cvs as $cv) {
                $cv = $path . '/' . $cv;
                array_push($cvs_arr, [$cv, $directory]);
            }
        }
        return $cvs_arr;
    }

    public function parseCvToXml($file)
    {
        if (is_string($file)) {
            $resumeFile = $file;
        } else {
            $resumeFile = $file->path();
        }

        $bb     = base64_encode(file_get_contents($resumeFile));
        $client = new \SoapClient('http://88.198.90.116/cvvalid/CVXtractorService.wsdl');
        $result = $client->ProcessCV(
            [
                'document_url' => $bb,
                'account'      => 'jobzella'
            ]
        );
        return $result->hrxml;
    }

    public function parseXmlToArr($xml)
    {
        $p = xml_parser_create('utf-8');
        xml_parse_into_struct($p, $xml, $values, $index);
        xml_parser_free($p);
        return $values;
    }

    public function getCvData()
    {
        $data['name']           = $this->getValue('FORMATTEDNAME');
        $data['date_of_birth']  = $this->getValue('ANYDATE');
        
        $data['marital_status'] = $this->getValue('MARITALSTATUS');
        $data['nationality']    = $this->getValue('NATIONALITY');
        $data['sex']            = $this->getValue('SEX');

        $data['email']          = $this->getValue('INTERNETEMAILADDRESS');

        $data['summary']        = $this->getValue('EXECUTIVESUMMARY');

        $data['objective']      = $this->getValue('OBJECTIVE');
        $data['job_title']      = $this->getValue('TITLE');
        
        $data['skills']         = $this->getSkillsArr('COMPETENCY');

        //Experiences
        $data['experiences'] = [];
        $experiences         = $this->getExperiencesArr('COMPETENCY');
        $locations           = array_pad($this->getValuesArr('MUNICIPALITY'), count($experiences), '');
        $companies_names     = array_pad($this->getValuesArr('EMPLOYERORGNAME'), count($experiences), '');
        $startYears          = array_pad($this->getStartYearsArr('STARTDATE'), count($experiences), NULL);
        $endYears            = array_pad($this->getEndYearsArr('ENDDATE'), count($experiences), NULL);
        $descriptions        = array_pad($this->getValuesArr('DESCRIPTION'), count($experiences), NULL);

        foreach ($experiences as $key => $experience) {
            array_push($data['experiences'], [
                'title'        => $experience,
                'location'     => $locations[$key],
                'company_name' => $companies_names[$key],
                'company_name' => $companies_names[$key],
                'period_from'  => $startYears[$key],
                'period_to'    => $endYears[$key],
                'description'  => $descriptions[$key],
            ]);
        }
        //End of experiences

        $data['degrees']       = $this->getDegreesArr('COMPETENCY');
        $data['organizations'] = $this->getValuesArr('EMPLOYERORGNAME');
        $data['languages']     = $this->getValuesArr('LANGUAGECODE');

        $data['school'] = [
            'name'        => $this->getValue('SCHOOLNAME'),
            'degree'      => $this->getValue('DEGREENAME'),
            'field_study' => $this->getValue('MAJOR'),
            'year'        => $this->getValue('YEAR'),
        ];

        $data['address'] = [
            'streetName'      => $this->getValue('STREETNAME'),
            'buildingNumber'  => $this->getValue('BUILDINGNUMBER'),
            'addressLine'     => $this->getValue('ADDRESSLINE'),
            'region'          => $this->getValue('REGION'),
            'postalCode'      => $this->getValue('POSTALCODE'),
            'countryCode'     => $this->getValue('COUNTRYCODE'),
            'formattedNumber' => $this->getValuesArr('FORMATTEDNUMBER'),
        ];
        return $data;
    }

    public function getValue($tag)
    {
        foreach ($this->xm2Arr as $line) {
            if ($line['tag'] == $tag) {
                    if(isset($line['value']))
                        return trim($line['value']);
                return '';
            }
        }
    }

    public function getSkillsArr($tag, $arr = [])
    {
        foreach ($this->xm2Arr as $line) {
            if ($line['tag'] == $tag) {
                if (isset($line['attributes']['NAME'])) {
                    array_push($arr, trim($line['attributes']['NAME']));
                }
            }
        }
        return $arr;
    }

    public function getValuesArr($tag, $arr = [])
    {
        foreach ($this->xm2Arr as $line) {
            if ($line['tag'] == $tag) {
                array_push($arr, trim($line['value']));
            }
        }
        return $arr;
    }

    public function getDegreesArr($tag, $arr = [])
    {
        foreach ($this->xm2Arr as $line) {
            if ($line['tag'] == $tag) {

                if (isset($line['attributes']['DESCRIPTION'])) {
                    if ($line['attributes']['DESCRIPTION'] == 'Degree/Qualification') {
                        if (isset($line['attributes']['NAME'])) {
                            array_push($arr, trim($line['attributes']['NAME']));
                        }
                    }
                }
            }
        }
        return $arr;
    }

    public function getExperiencesArr($tag, $arr = [])
    {
        foreach ($this->xm2Arr as $line) {
            if ($line['tag'] == $tag) {
                if (isset($line['attributes']['DESCRIPTION'])) {
                    if ($line['attributes']['DESCRIPTION'] == 'Held Position') {
                        if (isset($line['attributes']['NAME'])) {
                            array_push($arr, trim($line['attributes']['NAME']));
                        }
                    }
                }
            }
        }
        return $arr;
    }

    public function getStartYearsArr($tag, $arr = [])
    {
        foreach ($this->xm2Arr as $key => $line) {
            if ($line['tag'] == $tag) {
                if ($this->xm2Arr[$key + 1]['tag'] == 'YEARMONTH') {
                    $date = strtotime(trim($this->xm2Arr[$key + 1]['value']));
                    array_push($arr, date('Y-m-d', $date));
                }
            }
        }
        return $arr;
    }

    public function getEndYearsArr($tag, $arr = [])
    {
        foreach ($this->xm2Arr as $key => $line) {
            if ($line['tag'] == $tag) {

                //if the date is till present
                if (isset($line['value'])) {
                    array_push($arr, 1);
                } else {
                    if ($this->xm2Arr[$key + 1]['tag'] == 'YEARMONTH') {
                        $date = strtotime(trim($this->xm2Arr[$key + 1]['value']));
                        array_push($arr, date('Y-m-d', $date));
                    }
                }
            }
        }
        return $arr;
    }

    public function checkValue($val)
    {
        foreach ($this->xm2Arr as $line) {
            if (isset($line['value'])) {
                if (strpos($line['value'], $val) !== FALSE) {
                    return trim($line['value']);
                }
            }
        }
    }

    public function checkIfValidCv($info, $industry)
    {
        if ($info['email'] == '') {
            $this->error("The cv doesn't have an account email.");
            $this->line("\n");
            return FALSE;
        }

        if ($info['name'] == '') {
            $this->error("The cv doesn't have a valid name.");
            $this->line("\n");
            return FALSE;
        }

        if (DB::table('accounts')->where('email', $info['email'])->count() > 0) {
            $this->error("The cv exists before.");
            $this->line("\n");
            return FALSE;
        }

        if (!$this->getIndustryId($industry)) {
            $this->error('Cannot find industry : ' . $industry);
            $this->line("\n");
            return FALSE;
        }

        return TRUE;
    }

    public function generateUsername($name)
    {
        if ($name == NULL)
            return strtolower(str_random(8));

        $name = strtolower(str_replace(' ', '-', $name));
        if (DB::table('accounts')->where('username', $name)->count() > 0) {
            $name = $name . '-' . rand(10, 100);
            $this->generateUsername($name);
        }
        return $name;
    }

    public function getIndustryId($industry)
    {
        $industry = str_replace('_', '/', strtolower($industry));
        foreach (trans('industry') as $key => $val) {
            if (strtolower($val) == $industry) {
                $this->industry_id = $key;
                return TRUE;
            }
        }
        return FALSE;
    }

    public function insertIntoAccount($info)
    {
        $username = $this->generateUsername($info['name']);
        $password = strstr($info['email'], '@', TRUE);

        $account["username"]  = $username;
        $account["email"]     = $info['email'];
        $account["password"]  = $password;
        $account["activated"] = 1;
        $account              = (new \Core\Repositories\UserRepository())->addAccount($account);
        return [
            $account->id,
            $username,
            $password
        ];
    }

    public function insertIntoUsers($info, $account_id, $username)
    {
        return DB::table('users')->insertGetId([
                'username'       => $username,
                'email'          => $info['email'],
                'first_name'     => strstr($info['name'], ' ', TRUE),
                'last_name'      => trim(strstr($info['name'], ' ')),
                'account_id'     => $account_id,
                'referral_id'    => 999,
                'complete_steps' => 3,
                'created_at'     => new DateTime('now'),
            ]
        );
    }

    public function insertIntoUsersWithFullName($info, $account_id, $username)
    {
        return DB::table('users')->insertGetId([
                'username'       => $username,
                'email'          => $info['email'],
                'first_name'     => strtolower($info['first_name']),
                'last_name'      => strtolower($info['last_name']),
                'account_id'     => $account_id,
                'referral_id'    => 999,
                'complete_steps' => 3,
                'created_at'     => new DateTime('now'),
                'quick_register' => 1,
            ]
        );
    }

    public function updateaccountsUserId($account_id, $user_id)
    {
        DB::table('accounts')->where('id', $account_id)->update([
                'user_id'     => $user_id,
                'personal_id' => $user_id,
            ]
        );
    }

    public function insertIntoResumes($info, $user_id, $industry_id = 120)
    {
        if ($info['job_title'] == NULL)
            $job_title = '';
        else
            $job_title = substr($info['job_title'], 0, 100);

        if (empty($info['address']['formattedNumber']))
            $mobile = '';
        else
            $mobile = $info['address']['formattedNumber'][0];

        if ($info['date_of_birth'] == NULL)
            $date_of_birth = NULL;
        else {
            $date_of_birth = $info['date_of_birth'];
            if (strlen($date_of_birth) == 4)
                $date_of_birth = '01/01/' . $date_of_birth;

            $date_of_birth = date('Y-m-d', strtotime($date_of_birth));
        }

        $resume_data = [
            'member_id'               => $user_id,
            'email'                   => $info['email'],
            'job_title'               => $job_title,
            'first_name'              => strstr($info['name'], ' ', TRUE),
            'last_name'               => trim(strstr($info['name'], ' ')),
            'country'                 => ($info['address']['countryCode'] == NULL) ? '' : $info['address']['countryCode'],
            'city'                    => '',
            'address'                 => ($info['address']['streetName'] == NULL) ? '' : $info['address']['streetName'],
            'zipcode'                 => ($info['address']['postalCode'] == NULL) ? '' : $info['address']['postalCode'],
            'nationality'             => empty($info['nationality']) ? 'EG' : $info['nationality'],
            'mobile'                  => $mobile,
            'summary'                 => ($info['summary'] == NULL) ? '' : $info['summary'],
            'education'               => ($info['school']['name'] == NULL) ? 1 : 0,
            'experience'              => count($info['experiences']),
            'languages'               => count($info['languages']),
            'skills'                  => count($info['skills']),
            'date_of_birth'           => $date_of_birth,
            'courses'                 => 0,
            'job_industries_id'       => $industry_id,
            'interests'               => 0,
            'awards'                  => 0,
            'projects'                => 0,
            'completeness_score'      => 60,
            'searchable'              => 1,
            'confidential'            => 0,
            'statistics_applications' => 0,
            'statistics_searches'     => 0,
            'statistics_views'        => 0,
            'employment_type'         => 0,
            'empty_flag'              => 0,
            'keywords'                => '',
            'last_update'             => new DateTime('now'),
            'daxtra_id'               => '',
            'target_job'              => '',
            'target_expected_salary'  => '',
            'target_currency_salary'  => '',
            'target_country'          => '',
            'target_city'             => '',
            'cover_photo'             => '',
            'gov_id'                  => '',
            'day_phone'               => '',
            'evening_phone'           => '',
            'fax'                     => '',
            'number_of_dependants'    => '',
            'video'                   => '',
            'website'                 => '',
            'linkedin'                => '',
            'facebook'                => '',
            'twitter'                 => '',
            'google_plus'             => '',
            'xing'                    => '',
            'created_at'              => new DateTime('now'),
        ];

        switch (strtolower($info['sex'])) {
            case 'male':
                $resume_data['gender'] = 1;
                break;
            case 'female':
                $resume_data['gender'] = 2;
                break;
            default:
                $resume_data['gender'] = 0;
                break;
        }

        switch (strtolower($info['marital_status'])) {
            case 'single':
                $resume_data['marital_status'] = 1;
                break;
            case 'engaged':
                $resume_data['marital_status'] = 2;
                break;
            case 'married':
                $resume_data['marital_status'] = 3;
                break;
            default:
                $resume_data['marital_status'] = 4;
                break;
        }

        return DB::table('resumes')->insertGetId($resume_data);
    }

    public function insertIntoResumesWithFullName($info, $user_id, $industry_id = 120)
    {
        $resume_data                   = [
            'member_id'               => $user_id,
            'email'                   => $info['email'],
            'job_title'               => $info['mobile'],
            'first_name'              => strstr($info['first_name'], ' ', TRUE),
            'last_name'               => trim(strstr($info['last_name'], ' ')),
            'country'                 => '',
            'city'                    => '',
            'address'                 => '',
            'zipcode'                 => '',
            'nationality'             => 'EG',
            'mobile'                  => $info['mobile'],
            'summary'                 => '',
            'education'               => 0,
            'experience'              => 0,
            'languages'               => 0,
            'skills'                  => 0,
            'date_of_birth'           => NULL,
            'courses'                 => 0,
            'job_industries_id'       => $industry_id,
            'interests'               => 0,
            'awards'                  => 0,
            'projects'                => 0,
            'completeness_score'      => 60,
            'searchable'              => 1,
            'confidential'            => 0,
            'statistics_applications' => 0,
            'statistics_searches'     => 0,
            'statistics_views'        => 0,
            'employment_type'         => 0,
            'empty_flag'              => 0,
            'keywords'                => '',
            'last_update'             => new DateTime('now'),
            'daxtra_id'               => '',
            'target_job'              => '',
            'target_expected_salary'  => '',
            'target_currency_salary'  => '',
            'target_country'          => '',
            'target_city'             => '',
            'cover_photo'             => '',
            'gov_id'                  => '',
            'day_phone'               => '',
            'evening_phone'           => '',
            'fax'                     => '',
            'number_of_dependants'    => '',
            'video'                   => '',
            'website'                 => '',
            'linkedin'                => '',
            'facebook'                => '',
            'twitter'                 => '',
            'google_plus'             => '',
            'xing'                    => '',
            'created_at'              => new DateTime('now'),
        ];
        $resume_data['gender']         = 0;
        $resume_data['marital_status'] = 4;

        return DB::table('resumes')->insertGetId($resume_data);
    }


    public function insertIntoResumesEducations($info, $resume_id)
    {
        if ($info['school']['field_study'] == NULL)
            $field_study = '';
        else
            $field_study = substr($info['school']['field_study'], 0, 100);

        if ($info['school']['year'] == NULL)
            $year = NULL;
        else {
            $year = $info['school']['year'];
            if (strlen($year) == 4)
                $year = '01/01/' . $year;

            $year = date('Y-m-d', strtotime($year));
        }

        DB::table('resumes_educations')->insert([
                'resume_id'      => $resume_id,
                'school'         => ($info['school']['name'] == NULL) ? '' : $info['school']['name'],
                'degree'         => ($info['school']['degree'] == NULL) ? '' : $info['school']['degree'],
                'grade'          => '',
                'field_study'    => $field_study,
                'dates_attended' => $year,
                'created_at'     => new DateTime('now'),
            ]
        );
    }

    public function insertIntoResumesExperiences($info, $resume_id)
    {
        foreach ($info['experiences'] as $row) {
            DB::table('resumes_experiences')->insert([
                    'resume_id'    => $resume_id,
                    'title'        => $row['title'],
                    'location'     => $row['location'],
                    'company_name' => $row['company_name'],
                    'period_from'  => $row['period_from'],
                    'period_to'    => ($row['period_to'] == 1) ? NULL : $row['period_to'],
                    'to_present'   => ($row['period_to'] == 1) ? 1 : 0,
                    'description'  => ($row['description'] == NULL) ? '' : $row['description'],
                    'created_at'   => new DateTime('now'),
                ]
            );
        }
    }

    public function insertIntoResumesLanguages($info, $resume_id)
    {
        foreach ($info['languages'] as $row) {
            DB::table('resumes_languages')->insert([
                    'resume_id'     => $resume_id,
                    'language_name' => $row,
                    'proficiency'   => strtolower($row) == 'arabic' ? '1' : '3',
                    'created_at'    => new DateTime('now'),
                ]
            );
        }
    }

    public function insertIntoResumesSkills($info, $resume_id)
    {
        foreach ($info['skills'] as $row) {
            try {
                DB::table('resume_skills')->insert([
                        'resume_id'  => $resume_id,
                        'skill'      => $row,
                        'created_at' => new DateTime('now'),
                    ]
                );
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function extractImageFromCv($file, $resume_id)
    {
        if (pathinfo($file)['extension'] == 'pdf') {
            $this->extractImageFromPdf($file, $resume_id);
        } else {
            $this->extractImageFromDoc($file, $resume_id);
        }

    }

    public function extractImageFromDoc($file, $resume_id)
    {
        $zip = new \ZipArchive;

        if (TRUE === $zip->open($file)) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zip_element = $zip->statIndex($i);
                if (preg_match("([^\s]+(\.(?i)(jpg|jpeg|png|bmp))$)", $zip_element['name'])) {
                    $extention = pathinfo($zip_element['name'], PATHINFO_EXTENSION);
                    $image     = $zip->getFromIndex($i);
                    //check image size
                    if (!$this->checkImageSize(getimagesizefromstring($image)[0], getimagesizefromstring($image)[1]))
                        continue;

                    $image_name = rand() . '.' . $extention;
                    $image_path = public_path('uploads/profile_photos/' . date('Y') . '/' . date('M') . '/' . date('d'));
                    file_put_contents($image_path . '/' . $image_name, $image);
                    (new UploaderS3(new UploaderLocal()))->uploadOrignalImage($image_name, $image_path);
                    DB::table('resumes')->where('id', $resume_id)->update([
                            'photo' => str_replace(public_path() . '/uploads/', '', $image_path) . '/' . $image_name,
                        ]
                    );

                    $this->comment('Image found ^ _ ^ ');
                    break;
                }
            }
        } else {
            $this->comment('Image not found :(');
        }

    }

    public function extractImageFromPdf($file, $resume_id)
    {
        try{

        $pdf         = new PdfToText ($file, PdfToText::PDFOPT_DECODE_IMAGE_DATA);
        $image_count = count($pdf->Images);

        if ($image_count) {
            for ($i = 0; $i < $image_count; $i++) {

                $img = $pdf->Images [$i];

                //check image size
                if (!$this->checkImageSize(imagesx($img->ImageResource), imagesy($img->ImageResource)))
                    continue;


                $image_name = rand() . '.jpg';
                  
                $image_path = public_path('uploads/profile_photos/' . date('Y') . '/' . date('M') . '/' . date('d'));

                if (!file_exists($image_path)) {
                    mkdir($image_path, 0777, true);
                }

                $img->SaveAs($image_path . '/' . $image_name);
                (new UploaderS3(new UploaderLocal()))->uploadOrignalImage($image_name, $image_path);
                DB::table('resumes')->where('id', $resume_id)->update([
                        'photo' => str_replace(public_path() . '/uploads/', '', $image_path) . '/' . $image_name,
                    ]
                );


                $this->comment('Image found ^ _ ^ ');
                break;
            }
        } else {
            $this->comment('Image not found :(');
        }
    }
    catch(\Exception $e)
    {
        $this->comment('Something went wrong :(');
    }
        
    }

    public function checkImageSize($w, $h)
    {
        if ($w < 100 || $h < 100)
            return FALSE;
        return TRUE;
    }

    private function createCsvFileSheet()
    {
        $path = storage_path('cvs_sheet');

        if (!file_exists($path)) {
            mkdir( $path, 0777, true);
        }

        $this->csv_file_name =  $path .'/'. uniqid().'-export.csv';
        $csv_file            = fopen($this->csv_file_name, 'w');
        fputcsv($csv_file, ['account_id', 'user_id', 'email', 'name', 'user name', 'password', 'url']);
        return $csv_file;
    }

    private function addUserToCsvFile($csv_file, $account_id, $user_id, $email, $name, $username, $password)
    {
        fputcsv($csv_file, [
            $account_id,
            $user_id,
            $email,
            $name,
            $username,
            $password,
            URL::to($username)
        ]);
        $this->comment('The user data has been added to CSV file. D: Yeah :D');
    }

    private function downloadCsvFile($csv_file)
    {
        fclose($csv_file);

        $headers = [
            'Content-Type' => 'text/csv',
        ];
        return Response::download(storage_path($this->csv_file_name), 'export-cvs.csv', $headers);
    }

}


