<?php
namespace Core\Repositories;


use Accounts;
use Cartalyst\Sentry\Users\Kohana\User;
use Core\Enums\Lookup;
use Core\Repositories\Lookup\CitiesRepository;
use Core\Repositories\Lookup\CountriesRepository;
use Core\Repositories\ElasticSearch\EsPeopleRepository;
use DB;
use Employer;
use Misc;
use Resume;
use Sentry;
use URL;

class UserRepository
{
    private $database_connection;

    public function __construct()
    {
        $this->database_connection = new \User();
    }

    /**
     * insert account in accounts table
     * @param type $posted_data
     * @return type
     */
    public function addAccount($posted_data)
    {
        if ($posted_data) {
            foreach ($posted_data as $key => $value) {
                if ($value) {
                    $data[$key] = $value;
                }
            }

            if ($data) {
                $object = Sentry::register($data, false);
                $object->activationCode = $object->getActivationCode();

                return $object;
            }
        }
    }


    #WTF
    public function change($posted_data, $id)
    {
        if ($posted_data) {
            foreach ($posted_data as $key => $value) {
                if ($value) {
                    $data[$key] = $value;
                }
            }
            if ($data) {
                $object = $this->database_connection->where('id', '=', $id)->update($data);
            }

            return $object;
        }
    }

    //#old->get_data_by_email
    public function getUserLanguageTypeByEmail($email)
    {
        return $this->database_connection->where('email', '=', $email)->get(array('id', 'language', 'membership_type'));

    }

    //#old->get_id_by_email
    public function getUserIdByEmail($email)
    {
        return $this->database_connection->where('email', '=', $email)->pluck('id');
    }

//#old->check_email
    public function checkUniqueEmail($email)
    {
        $count = $this->database_connection->where('email', '=', $email)->count();
        if ($count == 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getAllDataById($id)
    {
        $data = array();
        $member_type = $this->getTypeById($id);
        if ($member_type == 1) {
            $data['member_type'] = true;
            $data['user_data'] = $this->database_connection->
            join('resumes as r', 'r.member_id', '=', 'users.id')
                ->where('users.id', '=', $id)
                ->get(array(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.username',
                    'users.membership_type',
                    'r.address',
                    'r.country',
                    'r.city',
                    'r.zipcode',
                    'r.mobile',
                    'r.photo AS seeker_photo',
                    'r.statistics_views',
                    'r.id AS resume_id',
                    'r.completeness_score',
                    'r.first_name AS resume_first_name',
                    'r.last_name AS resume_last_name',
                    'r.job_title AS title'
                ));
        } else {

            $data['member_type'] = false;
            $data['user_data'] = $this->database_connection->
            join('employers as e', 'e.member_id', '=', 'users.id')
                ->where('users.id', '=', $id)
                ->get(array(
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.username',
                    'users.membership_type',
                    'e.address',
                    'e.company_name',
                    'e.company_location',
                    'e.company_city',
                    'e.zip_code',
                    'e.company_number',
                    'e.direct_phone',
                    'e.mobile_phone',
                    'e.website',
                    'e.seen',
                    'e.logo AS company_logo',
                    'e.id as employer_id',
                    'e.email as e_email',
                    'e.first_name as e_first_name',
                    'e.last_name as e_last_name',
                ));

        }
        if ($data) {
            return $data;
        }

    }




	public function getAllDataByUserId($id)
	{
		$data = array();
		$member_type = $this->getTypeById($id);
		if ($member_type == 1) {
			$data['member_type'] = true;
			$data['user_data'] = $this->database_connection->
			join('resumes as r', 'r.member_id', '=', 'users.id')
				->where('users.id', '=', $id)
				->get()->toArray();
		} else {

			$data['member_type'] = false;
			$data['user_data'] = $this->database_connection->
			join('employers as e', 'e.member_id', '=', 'users.id')
				->where('users.id', '=', $id)
				->get()->toArray();

		}
		if ($data) {
			return $data;
		}

	}






    //#old->get_data_username
    public function getAllDataByUsername($username)
    {
        $data = array();
        if ($member_type = $this->getTypeByUsername($username) == 1) {
            $data['member_type'] = $member_type;
            $data['user_data'] = $this->database_connection->
            join('resumes as r', 'r.member_id', '=', 'users.id')
                ->where('users.username', '=', $username)
                ->get(array(
                    'users.id as member_id',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.username',
                    'users.membership_type',
                    'r.address',
                    'r.country',
                    'r.city',
                    'r.zipcode',
                    'r.mobile',
                    'r.photo AS seeker_photo',
                    'r.statistics_views',
                    'r.id AS resume_id',
                    'r.completeness_score',
                    'r.first_name AS resume_first_name',
                    'r.last_name AS resume_last_name'
                ));
        } else {

            $data['member_type'] = $member_type;
            $data['user_data'] = $this->database_connection->
            join('employers as e', 'e.member_id', '=', 'users.id')
                ->where('users.username', '=', $username)
                ->get(array(
                    'users.id as member_id ',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.username',
                    'e.address',
                    'e.company_name',
                    'e.company_location',
                    'e.company_city',
                    'e.zip_code',
                    'e.company_number',
                    'e.website',
                    'e.seen',
                    'e.logo AS company_logo',
                    'e.id as employer_id'

                ));

        }
        if ($data) {
            return $data;
        }

    }

//#old->check_activation
    public function checkUserActivationByUserId($user_id)
    {
        $status = $this->database_connection->where('id', '=', $user_id)->pluck('status');
        if (isset($status) && $status == 1) {
            return true;
        }

        return false;
    }

//#old->check_activation_emp
    public function checkUserActivationByEmployerId($employer_id)
    {
        $id = $this->getUserIdByEmployerId($employer_id);
        $status = $this->database_connection->where('id', '=', $id)->pluck('status');
        if (isset($status) && $status == 1) {
            return true;
        }

        return false;
    }

    public function getUserStatusByUsername($username)
    {
        $status = $this->database_connection->where('username', '=', $username)->pluck('status');
        if (isset($status)) {
            return $status;
        }
    }

//#old->check_username
    public function checkUniqueUsername($username)
    {
        $count = $this->database_connection->where('username', '=', $username)->count();
        if ($count == 0) {
            return true;
        } else {
            return false;
        }
    }

//#old->get_id
    public function getIdByUserName($username)
    {
        $id = $this->database_connection->where('username', '=', $username)->pluck('id');
        if (isset($id)) {
            return $id;
        } else {
            return false;
        }

    }

//#old->member_type
    public function getMemberShipTypeByUsername($username)
    {
        $type = $this->database_connection->where('username', '=', $username)->pluck('membership_type');
        if ($type) {
            return $type;
        }
    }

    public function getMemberShipTypeByUserId($user_id)
    {
        $type = $this->database_connection->where('id', '=', $user_id)->pluck('membership_type');
        if ($type) {
            return $type;
        } else {
            return false;
        }
    }

    public function getUsername($user_id)
    {
        $user_name = $this->database_connection->where('id', '=', $user_id)->pluck('username');

        if (!empty($user_name)) {
            return $user_name;
        }

    }

    public function getAccountByUserId($user_id)
    {
        return Accounts::where( 'user_id', $user_id )->first();
    }

    /**
     * @author Islam Ahmed Kandil
     * @param $user_id
     * @return mixed
     */
    public function getAccountProfile($user_id)
    {
        return \Member::find($user_id)->employer()->first();
    }

    public function saveAccountProfile($save_data, $user_id)
    {
        if (is_array($save_data)) {
            $company_info = $this->getAccountProfile($user_id);
            foreach ($save_data as $key => $val) {
                if (isset($company_info->$key)) {
                    $company_info->$key = $val;
                }
            }
            if ($company_info->save()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @author Islam Kandel
     * @param $user_data
     * @return \Illuminate\Database\Eloquent\Model|int|static
     */
    public static function addUser($user_data)
    {
        return \User::create($user_data);
    }

    /**
     * @author islam kandel
     * @param $user_id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getUnActiveUserById($user_id)
    {
        return \User::where('status', '!=', '1')->where('id', '=', $user_id)->first();
    }

    /**
     * @author islam kandel
     * @param $user_id
     * @return mixed
     */
    public function generateActivationCode($user_id)
    {
        \code::whereMemberId($user_id)->delete();
        $code_string = \Misc::generate_random_string();
        $code['code'] = $code_string;
        $code['member_id'] = $user_id;
        $code['valid'] = 24;
        \code::add($code);

        return $code;
    }
    /**
     * function that return specific field .
     * @param type $field the field you want to return back
     * @param type $id ->the id of user
     * @return with the value on the field or false
     * @author Elgendy         /
     */
    //#old->get
    public function getSpecificFieldById($field_name, $user_id)
    {
        $field_data = DB::table('users')->where('id', '=', $user_id)->pluck($field_name);
        if ($field_data) {
            return $field_data;
        } else {
            return false;
        }

    }

//old->get_email
    public function getEmailById($user_id)
    {
        $user_email = DB::table('users')->where('id', '=', $user_id)->pluck('email');
        if ($user_email) {
            return $user_email;
        }

    }

    public function getUserEmailById($user_id)
    {
        return $this->database_connection->where('id', '=', $user_id)->pluck('email');

    }

    public function getUserWithField($name_field, $field)
    {
        return $this->database_connection->where("$name_field", '=', $field)->pluck('id');

    }

//#old->get_type
    public function getTypeById($user_id)
    {
        $membership_type = $this->database_connection->where('id', '=', $user_id)->pluck('membership_type');
        if ($membership_type) {
            return $membership_type;
        }
    }

//#old->get_type_username
//todo#directDB
    public function getTypeByUsername($username)
    {
        $membership_type = $this->database_connection->where('username', '=', $username)->pluck('membership_type');
        if ($membership_type) {
            return $membership_type;
        }
    }

    public function getLogo($user_id, $w = '200', $h = '200', $q = '90')
    {

        $type = $this->getTypeById($user_id);
        if ($type == 1) {
            return Resume::seeker_photo($user_id, $w, $h, $q);
        } else {
            return Employer::employer_logo($user_id, $w, $h, $q);
        }
    }


    public function getCoverPhoto($user_id, $type = null)
    {
        if ($type == null) {
            $type = $this->getMemberShipTypeByUserId($user_id);
        }
        if ($type == 1) {
            $cover_photo = Resume::where('member_id', '=', $user_id)->pluck(('cover_photo'));
        } else {
            $cover_photo = Employer::where('member_id', '=', $user_id)->pluck(('cover_photo'));
        }

        if ($cover_photo) {
            if ($cover_photo != null) {
                if ($type == 1) {
	                return 'uploads/' . $cover_photo;
                    if (Misc::checkValidUrl(asset('uploads/' . $cover_photo))) {
                        return 'uploads/' . $cover_photo;
                    } else {
                        return asset('ui-assets/images/default_covers/default-cover.jpg');

                        return 'jobzella/img/998e2bda.User-profile.png';
                    }

                } else {
	                return 'uploads/employers/' . $user_id . '/' . $cover_photo;
                    if (Misc::checkValidUrl(asset('uploads/employers/' . $user_id . '/' . $cover_photo))) {
                        return 'uploads/employers/' . $user_id . '/' . $cover_photo;
                    } else {
//                            return asset('ui-assets/images/default_covers/default-cover.jpg');

//                            return 'jobzella/img/998e2bda.User-profile.png';
                    }
                }
            } else {
                return asset('ui-assets/images/default_covers/default-cover.jpg');

                return 'img/cover.jpg';
            }
        }
        return false;


    }

    //new
    public function getCompanyNameByEmployerId($employer_id)
    {
        $company_name = Employer::where('id', '=', $employer_id)->pluck('company_name');
        if (isset($company_name)) {
            return $company_name;
        } else {
            return false;
        }
    }

    #old->get_company_name()
    public function getCompanyNameByUserId($user_id)
    {
        $company_name = Employer::where('member_id', '=', $user_id)->pluck('company_name');
        if (isset($company_name)) {
            if ($company_name) {
                return $company_name;
            } else {
                return false;
            }
        }
    }

    /**
     * @param $user_id
     * @return mixed
     */
    public function getCreateAtByUserId($user_id)
    {
        return Employer::where('member_id', '=', $user_id)->pluck('created_at');
    }

    /**
     * @param $user_id
     * @return mixed
     */
    public function getSeenByUserId($user_id)
    {

        $membership_type = $this->getMemberShipTypeByUserId($user_id);
        if ($membership_type == 1) {
            return Resume::where('member_id', '=', $user_id)->pluck('statistics_views');

        } else {
            return Employer::where('member_id', '=', $user_id)->pluck('seen');
        }

    }

    /**
     * @param $user_id
     * @return mixed|string
     */
    public function getIndustryById($user_id)
    {
        $industry = Employer::where('member_id', '=', $user_id)->pluck('industry');
        if (empty($industry)) {
            return '';
        } else {
            return Lookup::getIndustryByID($industry);
        }
    }

    /**
     * @param $user_id
     * @return mixed|string
     */
    public function getCompanyCityById($user_id)
    {
        $city = Employer::where('member_id', '=', $user_id)->pluck('company_city');
        if (empty($city)) {
            return '';
        } else {
            return (new CitiesRepository())->getCityNameByCode($city);
        }
    }

    /**
     * @param $user_id
     * @return mixed|string
     */
    public function getCompanyCountryById($user_id)
    {
        $company_location = Employer::where('member_id', '=', $user_id)->pluck('company_location');
        if (empty($company_location)) {
            return '';
        } else {
            return (new CountriesRepository())->getCountryNameByCode($company_location);
        }
    }
    /*
     * <?php $value->membership_type == 1 ? print($value->first_name.' '.$value->last_name) : print $this->database_connection->getCompanyNameByUserId($value->member_id);

     ?>
     * */
    //#old->name_timeline
    public function getUserTimeLineName($user_id)
    {
        $name = $this->database_connection->where('id', '=', $user_id)->where('membership_type', '=',
            '1')->get(array('first_name', 'last_name'));

        if (count($name) > 0) {
            return $name[0]->first_name . ' ' . $name[0]->last_name;
        } else {
            return $this->getCompanyNameByUserId($user_id);
        }
    }

    public function getUserFirstLastNameById($user_id)
    {
        return $this->database_connection->where('id', '=', $user_id)->first(array('first_name', 'last_name'));

    }

    //#old->get_profile
    public function getProfileLink($user_id)
    {
        $username = $this->getUsername($user_id);
        if (!empty($username)) {
            return URL::to(urlencode($username));
        }
    }

    //new
    public function getEmployerProfileLink($employer_id)
    {
        $user_id = $this->getUserIdByEmployerId($employer_id);
        $temp = $this->getAllDataById($user_id);

        if (isset($temp['user_data'][0]->username) && !empty($temp['user_data'][0]->username)) {
            return URL::to(urlencode($temp['user_data'][0]->username));
        }

    }

    //new
    //old->get_memb_id()
    public function getUserIdByEmployerId($employer_id)
    {
        return Employer::where('id', '=', $employer_id)->pluck('member_id');
    }

    public function getEmployerIdByUserId($user_id)
    {
        return Employer::where('member_id', '=', $user_id)->pluck('id');
    }

    //#Doubled ->getLocalCompaniesForMail
    public function getLocalCompanies($country_code, $type, $take = 12)
    {
        $query = DB::table('hiring_now_on_jobzella');
        $query->whereIn('membership_type', $type);
        $query->where('jobs_count', '>', '0');
        if ($country_code != false) {
            $query->orderByRaw("company_location = 'sa' DESC");
        }
        $query->orderBy('jobs_count', 'desc');

        $query->select(array('*', 'user_id as member_id'));

        return $query->take($take)->get();

    }

    /*
    //#Doubled
    public function getLocalCompaniesForMail($country_code, $type)
    {
        $query = DB::table('hiring_now_on_jobzella');
        $query->whereIn('membership_type', $type);
        $query->where('jobs_count', '>', '0');
        if ($country_code != false) {
            $query->orderByRaw("company_location = 'sa' DESC");
        }
        $query->orderBy('jobs_count', 'desc');
        $query->select(array('*', 'user_id as member_id'));
        return $query->take(12)->get();

    }*/

    public function getUsersDataForReplay($user_id, $num = 25, $skip = 0)
    {
        //#useView
        $query = $this->database_connection->leftJoin('resumes as r', 'r.member_id', '=',
            'users.id')->leftJoin('employers as e', 'e.member_id', '=', 'users.id')->where('users.status', '=',
            1)->where('users.id', '=', $user_id)->groupBy('users.id')->select(array(
            'users.membership_type',
            'users.first_name',
            'users.last_name',
            'users.username',
            'r.first_name AS resume_first_name',
            'r.last_name AS resume_last_name',
            'r.photo AS seeker_photo',
            'users.id',
            'r.statistics_views',
            'e.seen',
            'e.company_name',
            'e.logo AS company_logo',
        ));
        if ($num > 0) {
            $query->take($num)->skip($skip);
        }

        return $query->get();
    }


    /**
     * get path of logo
     * @param int $user_id
     * @param int $user_type
     * @param string $photo_link
     * @static
     * @return string image path
     */
    public function getPhotoPath($user_id, $user_type, $photo_link = '', $w = 'auto', $h = 'auto', $q = '90')
    {

        if ($photo_link != '' && $photo_link != null) {
            if ($user_type != 1) {
                return URL::to('thumb/?src=uploads/employers/' . $user_id . '/' . $photo_link . '&amp;w=' . $w . '&amp;h=' . $h . '&amp;q=' . $q);
            } else {
                return URL::to('thumb/?src=uploads/' . $photo_link . '&amp;w=' . $w . '&amp;h=' . $h . '&amp;q=' . $q);
            }

        }

        return URL::to('thumb/?src=ui-assets/images/default_icons/default_photo.png' . '&amp;w=' . $w . '&amp;h=' . $h . '&amp;q=' . $q);

    }

    /**
     * get employer object by employer_id
     * @param type $id
     * @return employer object
     */
    public function getCompanyInformationByEmployerId($employer_id)
    {
        return Employer::where('id', '=', $employer_id)->first();
    }

    public function getCompanyInformationByUserId($id)
    {
        return Employer::where('member_id', '=', $id)->first();
    }

    /**
     * get user object by id
     * @param type $id
     * @return user object
     */
    public function getUserByID($id)
    {
        return ($this->database_connection->where('id', '=', $id)
            ->first());
    }
    /**
     * get user object by id
     * @param type $id
     * @return user object
     */
    public function getUserByAccountID($accountid)
    {
        return ($this->database_connection->where('account_id', '=', $accountid)
            ->first());
    }

    public function getAllUsersByAccountID($accountid)
    {
        return ($this->database_connection->where('account_id', '=', $accountid)->get());
    }

    //todo-me
    public function getnameType($user_id)
    {
        $type = $this->getTypeById($user_id);

        if ($type == 1) {
            return $this->getUsername($user_id);
        } else {
            return $this->getCompanyNameByUserId($user_id);
        }
    }

    /**
     * get user name by account_id
     * @param $account_id
     * @return string username
     */
    public function getUserNameByAcountId($account_id)
    {
        return $this->database_connection->where('account_id', '=', $account_id)->pluck('username');

    }

    /**
     * get user_id by account_id
     * @param $account_id
     * @return int account_id
     */
    public function getUserIdByAcountId($account_id)
    {
        return $this->database_connection->where('account_id', '=', $account_id)->pluck('id');

    }

    /**
     * @param $user_id
     * @return mixed
     */
    public function getAccountIdByUserId($user_id)
    {
        return $this->database_connection->where('id', '=', $user_id)->pluck('account_id');

    }

    /**
     *
     * @author islam ahmed kandel
     * @param $username
     */
    public function getUserDataFromUserName($username)
    {
        return $this->database_connection->where('username', '=', $username)->first();
    }


    /**
     * @param $user_id
     * @return \stdClass
     */
    public function generateUserCard($user_id)
    {
        $membership = $this->getMemberShipTypeByUserId($user_id);

        $value = new \stdClass();

        $value->member_id = $user_id;
        $value->membership_type = $membership;

        $resmeRepo = new ResumeRepository();
        $followRepo = new FollowRepository($user_id);


        $value->logo = asset($this->getLogo($user_id, 70, 70));
        $value->profileLink = $this->getProfileLink($user_id);

        $value->num_followers = $followRepo->getFollowerCount();
        $value->num_following = $followRepo->getFollowingCount();
        $value->num_appreciate = (new AppreciateRepository($user_id))->getAppreciatorsCount();

        $value->name = $this->getUserTimeLineName($user_id);
        $value->num_seen = $this->getSeenByUserId($user_id);

        if ($membership == 1) {


//            $value->last_experience = $resmeRepo->getLastExperience($user_id, true);
//            dd($resmeRepo->getUserCardDataByUserId($user_id));
            $userCardDataResume = $resmeRepo->getUserCardDataByUserId($user_id);
            $value->title = $userCardDataResume['job_title'];
            $value->city = isset($userCardDataResume['city']) ? (new CitiesRepository())->getCityNameByCode($userCardDataResume['city']) : '';
            $value->country = isset($userCardDataResume['country']) ? (new CountriesRepository())->getCountryNameByCode($userCardDataResume['country']) : '';
            $value->skills = isset($userCardDataResume['id']) ? $resmeRepo->getSkills($userCardDataResume['id']): '';
//            $value->location= $resmeRepo->getLastExperience($user_id, true)->location;


        } else {

            $value->industry = $this->getIndustryById($user_id);
            $value->city = $this->getCompanyCityById($user_id);
            $value->country = $this->getCompanyCountryById($user_id);


        }

        return $value;

    }

    public function getUserIdByResumeId($resume_id)
    {
        return Resume::where('id', '=', $resume_id)->pluck('member_id');
    }


    /*
     *todo check again , because it's better to get num of seen from users table (both for seekers & employers)
     */
    public function increamentNumSeenEmployer($profile_id)
    {
        return Employer::where('member_id', '=', $profile_id)->increment('seen');

    }

    public function getUsernameByUserId($id)
    {
        $user_name = $this->database_connection->where('id', '=', $id)->get(array('username'));

        if (!empty($user_name)) {
            return $user_name[0]->username;
        }

    }

    public function updateUsernameByUserId($id, $new_username)
    {
        $user = $this->database_connection->find($id);
        if ($user) {
            $user->username = $new_username;
            $update = $user->save();
            return $update;
        }
        return false;
    }

    public function updateEmailByUserId($id, $email)
    {
        $user = $this->database_connection->find($id);
        if ($user) {
            $user->email = $email;
            $update = $user->save();
            return $update;
        }
        return false;
    }

    public function deletePermissions($user_id)
    {
        return DB::table('permissions')->where('user_id','=',$user_id)->delete();
    }

    public function deleteUser($member_id)
    {
        $resume_obj = new ResumeRepository();

        EventsRepository::deleteEventsByMemberId($member_id);//delete user events

        JobsRepository::deleteJobsByMemberId($member_id); //delete user jobs

        $resume_obj->deleteResumeByUserId($member_id);//delete resume data

        SavedCoursesRepository::deleteSavedCoursesByMemberId($member_id);//delete saved courses

        SavedJobsRepository::deleteSavedJobsByMemberId($member_id); //delete saved jobs

        CoursesRepository::deleteCoursesByMemberId($member_id);//delete courses

        $this->deletePermissions($member_id);// delete user permissions

        $this->deleteUserAlerts($member_id); //delete user alerts

        $this->deleteUserAppreciations($member_id);//delete user appreciations on jobs, services and courses

        $this->deleteUserSocialData($member_id);//delete user like on comment and user comments and user stream data

        /*--delete from users table--*/
        $this->database_connection->where('id','=',$member_id)->delete();

        return ['response'=>'user deleted','code'=>1];
    }

    public function deleteAccount($account_id,$member_id)
    {
        $response = $this->deleteUser($member_id);

        $this->deleteSocialConnection($account_id);
        $this->deleteAffiliatesUrlMember($account_id);

        if(!(DB::table('accounts')->where('id','=',$account_id)->delete()))
            $response = ['response'=>'can not delete user account','code'=>0];

        return $response;
    }

    public function getUserByEmail($email)
    {
        return  $this->database_connection->where('email','=',$email)->first();
    }

    public function getUserByUsername($username)
    {
        return  $this->database_connection->where('username','=',$username)->first();
    }

    public static function getAccountByUsername($username)
    {
        return (array) DB::table('accounts')->where('username','=',$username)->first();
    }

    public static function getAccountByEmail($email)
    {
        return (array) DB::table('accounts')->where('email','=',$email)->first();
    }

    public static function hasPersonalAccount($account_id)
    {
       $res = DB::table('accounts')->find($account_id);
            return (int)$res->personal_id;
    }

    public static function deleteSocialConnection($account_id)
    {
        return DB::table('social')->where('user_id','=',$account_id)->delete();
    }

    public static function deleteUserStreamPosts($user_id)
    {
        return DB::table('stream')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserComments($user_id)
    {
        return DB::table('comments')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserLikesOnComments($user_id)
    {
        return DB::table('like_comment')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserStreamLike($user_id)
    {
        return DB::table('likes')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserLikesOnProfile($user_id)
    {
        return DB::table('like_profile')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserAppreciationOnCourses($user_id)
    {
        return DB::table('course_appreciate')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserAppreciationOnJobs($user_id)
    {
        return DB::table('job_appreciate')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserAppreciationOnServices($user_id)
    {
        return DB::table('service_appreciate')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteUserFromCourseApplicant($user_id)
    {
        return DB::table('course_applicants')->where('member_id','=',$user_id)->delete();
    }

    public static function deleteAffiliatesUrlMember($account_id)
    {
        DB::table('affiliates_url_members')->where('members_id','=',$account_id)->delete();
    }

    public function setAccountActive($account_id)
    {
        return  DB::table('accounts')->where('id','=',$account_id)->update(['activated'=>1]);
    }

    public function setAccountVerified($account_id)
    {
        return  DB::table('users')->where('account_id','=',$account_id)->update(['status'=>1]);

    }

    public function checkUserVerified($account_id)
    {
        $status= DB::table('users')->where('id','=',$account_id)->pluck('status');
        if($status!=1){
            return false;
        }else{
            return true;
        }

    }

    public function setCompleteStep($user_id, $step)
    {
        return DB::table('users')->where('id','=',$user_id)->update(['complete_steps' => $step]);
    }

    public function setLanguage($user_id, $lang)
    {
        return DB::table('users')->where('id','=',$user_id)->update(['language' => $lang]);
    }

    public function getLanguage($user_id)
    {
        $user = DB::table('users')->where('id','=',$user_id)->first();
        return (isset($user->language) && !empty($user->language)) ? $user->language : 'en';
    }

    public function removeThrottleAccount($account_id)
    {
        return DB::table('throttle')->where('user_id','=',$account_id)->delete();
    }

    public function deleteUserSocialData($user_id)
    {
        UserRepository::deleteUserComments($user_id);
        UserRepository::deleteUserLikesOnComments($user_id);
        UserRepository::deleteUserStreamLike($user_id);
        UserRepository::deleteUserStreamPosts($user_id);
    }

    public function deleteUserAppreciations($user_id)
    {
        UserRepository::deleteUserLikesOnProfile($user_id);
        UserRepository::deleteUserAppreciationOnCourses($user_id);
        UserRepository::deleteUserAppreciationOnJobs($user_id);
        UserRepository::deleteUserAppreciationOnServices($user_id);
    }

    public function deleteUserAlerts($user_id)
    {
        (new \Core\Repositories\JobAlertsRepository())->deleteUserAlerts($user_id);
        (new \Core\Repositories\ServicesAlertsRepository())->deleteUserAlerts($user_id);
        (new \Core\Repositories\CoursesAlertsRepository())->deleteUserAlerts($user_id);
    }
}