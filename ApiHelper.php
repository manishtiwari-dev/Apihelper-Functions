<?php

namespace App\Helper;

use App\Events\PrEventInternal;
use App\Models\Currency;
use App\Models\IndustryModule;
use App\Models\Language;
use App\Models\ModuleSection;
use App\Models\Notification;
use App\Models\NotificationMessage;
use App\Models\Setting;
use Modules\WebsiteSetting\Models\SuperEmailGroup;
use App\Models\Super\DateFormat;
use App\Models\Super\SuperTempImages;
use App\Models\TempImages;
use App\Models\Translation;
use App\Models\User;
use App\Models\UserBusiness;
use Modules\WebsiteSetting\Models\WebsiteSettingKeyValue;
use DB;
use Illuminate\Support\Facades\Storage;
use Image;
use Modules\UserManage\Models\AppPermissionType;
use Modules\UserManage\Models\Permission;
use Modules\UserManage\Models\Role;
use Modules\CRM\Models\CRMSettingTaxGroup;
use Modules\AddOnManager\Models\AddOnSetting;
use App\Models\Country;
use App\Models\SettingKeyValue;
use App\Models\Super\AppSettingGroupKey;
use Modules\CRM\Models\AppInvoices;
use Modules\WebsiteSetting\Models\Super\LandingImages;
use Modules\WebsiteSetting\Models\WebPages;
use Illuminate\Support\Str;


class ApiHelper
{

    public static $subscription = [];

    public static $industry = [];


    public static function init($token)
    {
        if (empty(self::$subscription)) {

            $subscription_details = self::get_subscription_details_by_api_token($token);
            self::$subscription = $subscription_details;
        }
    }

    public static function initial($token)
    {
        if (empty(self::$industry)) {

            $industry_details = self::get_industry_id_by_api_token($token);
            self::$industry = $industry_details;
        }
    }




    /*
    api token validate
     */
    public static function api_token_validate($token)
    {
        $token_decodeval = base64_decode($token);
        $token_ary = explode('_DB', $token_decodeval);

        Self::essential_config_regenerate($token_ary[1]);

        self::init($token_ary[0]);
        $user = User::where('api_token', $token_ary[0])->first();
        return !empty($user) ? true : false;
    }

    public static function get_api_token($token)
    {
        $token_decodeval = base64_decode($token);
        $token_ary = explode('_DB', $token_decodeval);

        if (isset($token_ary[0])) {
            return $token_ary[0];
        } else {
            return null;
        }
    }
    /*
    check user_id has parent or not
     */
    public static function get_parent_id($user_id)
    {
        $user = User::find($user_id);
        return !empty($user->created_by) ? $user->created_by : $user->id;
    }
    public static function get_parent_email($user_id)
    {
        $user = User::find($user_id);
        if ($user->created_by) {
            $parent_user = User::find($user->created_by);
            return $parent_user->email;
        } else {
            return $user->email;
        }
    }

    /* get user_id from token  */
    public static function get_user_id_from_token($token)
    {
        $user = User::where('api_token', $token)->first();
        return $user->id;
    }


    /* get user_id from token  */
    public static function is_module($token)
    {

        $industry_id = Self::get_industry_id_by_api_token($token);

        $indModule = IndustryModule::where(['industry_id' => $industry_id, 'module_id' => 5])->first();

        return $indModule;
    }




    /* get parent_id from token */
    public static function get_parentid_from_token($token)
    {
        $user = User::where('api_token', $token)->first();
        return Self::get_parent_id($user->id);
    }

    /* get parent_id from token */
    public static function get_parentemail_from_token($token)
    {
        $user = User::where('api_token', $token)->first();
        return Self::get_parent_email($user->id);
    }

    /* get admin_id from token */
    public static function get_adminid_from_token($token)
    {
        $user = User::where('api_token', $token)->first();
        if ($user != null) {
            $role = $user->roles[0]->roles_key;
            if ($role == "super_admin") {
                return $user->id;
            } else {
                return $user->created_by;
            }
        } else {
            return 0;
        }
    }

    public static function ModuleSectionList()
    {
        $newArray = [];
        $list = ModuleSection::select('section_slug', 'section_id')->where('parent_section_id', 0)->get();
        if (!empty($list)) {
            foreach ($list as $key => $ls) {
                $newArray[$ls->section_id] = $ls->section_slug;
            }
        }
        return $newArray;
    }

    /*
    crate custom json reponse
     */
    public static function JSON_RESPONSE($status, $data = array(), $msg)
    {
        return response()->json([
            'status' => $status,
            'data' => $data,
            'message' => $msg,
        ], ($status) ? 200 : 201);
    }

    /*
    get role name of user
     */
    public static function get_role_from_token($token)
    {

        $user = User::where('api_token', $token)->first();
        // return $user;

        if (isset($user->roles[0])) {

            return $user->roles[0]->roles_key;
        } else {
            return '';
        }
    }





    public static function get_role_name_from_token($token)
    {
        $user = User::where('api_token', $token)->first();
        // return $user;

        if (isset($user->roles[0])) {
            return $user->roles[0]->roles_name;
        } else {
            return '';
        }
    }

    public static function staff_role($role_id)
    {
        $user = User::where('id', $role_id)->first();
        // return $user;

        if (isset($user->roles[0])) {
            return $user->roles[0]->roles_name;
        } else {
            return '';
        }
    }

    /*
    function:get_permission_list
     */
    public static function get_permission_list($token)
    {
        $permission_array = [];
        $user = User::where('api_token', $token)->first();
        if (isset($user->roles[0])) {

            $section_list = Self::byRoleIdSectionsPermissionList($user->roles[0]->roles_id);
            foreach ($section_list as $sec) {
                $permissionIDsd = [];
                foreach ($sec->permissions as $key => $per) {
                    $permissionIDsd[$per->permissions_ids] = $per->permission_type_name;
                }
                $permission_array[$sec->section_id] = $permissionIDsd;
            }
        }
        return $permission_array;
    }

    public static function byRoleIdSectionsPermissionList($role_id)
    {
        $role = Role::find($role_id);
        if (!empty($role)) {
            $section = $role->sections()->groupBy('section_id')->get();
            // $section = DB::table(config('dbtable.common_role_has_permissions'))->where('roles_id',$role_id)->groupBy('section_id')->get();
            if (!empty($section)) {
                foreach ($section as $key => $sec) {
                    $section[$key]->permissions = Self::byRoleSectionIdPermission($role_id, $sec->section_id);
                }
            }
            return $section;
        } else {
            return [];
        }
    }

    public static function byRoleSectionIdPermission($roleid, $sectionid)
    {

        $permissionList = DB::table(config('dbtable.common_role_has_permissions'))->where('roles_id', $roleid)->where('section_id', $sectionid)->get();
        $permission = $permissionList->map(function ($per) {
            $per->permissions_name = Permission::find($per->permissions_ids)->permissions_name;

            $ptype = AppPermissionType::find($per->permission_types_id);

            $per->permission_type_name = !empty($ptype) ? $ptype->name : 'none';
            return $per;
        });
        return $permission;
    }

    /*
    check user have permission access or not via user token,page_name
     */
    public static function is_page_access($token, $page, $permission_slug)
    {

        $role_name = Self::get_role_from_token($token);

        if ($role_name == 'admin' || $role_name == 'super_admin') {
            return true;
        }
        // if super_admin

        $permissionInfo = Permission::where('permissions_slug', $permission_slug)->first();
        //  return   $permissionInfo;

        $moduleSection = ModuleSection::where('section_slug', $page)->first();

        if ($permissionInfo == null || $moduleSection == null) {
            return false;
        }

        $permission_list = Self::get_permission_list($token); // get permission_list


        if ($moduleSection->section_id == 1) {
            return true;
        } else if (isset($permission_list[$moduleSection->section_id][$permissionInfo->permissions_id])) {
            return $permission_list[$moduleSection->section_id][$permissionInfo->permissions_id];
        } else {
            return false;
        }
    }

    /*
    check user have permission access or not via user token,page_name
     */
    public static function is_page_access_val($token, $page, $permission_slug)
    {

        $role_name = Self::get_role_from_token($token);

        if ($role_name == 'admin' || $role_name == 'super_admin') {
            return true;
        }
        // if super_admin

        $permissionInfo = Permission::where('permissions_slug', $permission_slug)->first();
        $moduleSection = ModuleSection::where('section_slug', $page)->first();

        if ($permissionInfo == null || $moduleSection == null) {
            return false;
        }

        $permission_list = Self::get_permission_list($token); // get permission_list
        if ($permission_list[$moduleSection->section_id][$permissionInfo->permissions_id] !== "none") {
            return $permission_list[$moduleSection->section_id][$permissionInfo->permissions_id];
        } else {
            return "none";
        }
    }
    /*

    function: attach_query_permission_filter ( attach listing filter,  by added,owned,all,both )
     */
    public static function attach_query_permission_filter($query, $token, $page, $permission_slug)
    {

        // get permission type value to filter records
        $permission_res = Self::is_page_access_val($token, $page, $permission_slug);

        /*  Filter Records According to owned(parent_user) and added(current_user) */
        if ($permission_res == 'added' || $permission_res == 'owned') {

            $functionName = ($permission_res == 'owned') ? 'get_adminid_from_token' : 'get_user_id_from_token';
            $query = $query->where('created_by', Self::$functionName($token));
        } elseif ($permission_res == 'both') {

            $inWhere = Self::get_adminid_from_token($token) . "," . Self::get_user_id_from_token($token);
            $query = $query->whereRaw('created_by IN (' . $inWhere . ')');
        }

        return $query;
    }

    public static function user_login_history($response)
    {
        return $response;
    }

    public static function essential_config_regenerate($db_id)
    {

        $sub_prefix = env('DB_SUBSCRIBER_PREFIX', 'lab_subs_');
        $dbsuperadmin = env('DB_SUPERADMIN', 'lab_superadmin');
        $dblanding = env('DB_LANDING', 'lab_landing');

        if (isset($db_id) && $db_id > 0) {

            //SUBSCRIBER DATABASE
            $db_name = $sub_prefix . $db_id;

            //USER RELATED TABLE
            $users_table = $db_name . '.usr_users';

            $roles_table = $db_name . '.usr_roles';
            //$permissions_table = $db_name . '.usr_permissions';
            //$permissions_table = $db_name . '.app_permissions';

            //$users = $db_name.'.users';

            $user_has_roles_table = $db_name . '.usr_user_has_roles';
            $role_has_permissions_table = $db_name . '.usr_role_has_permissions';

            $user_logins_table = $db_name . '.usr_user_logins';

            //APP RELATED TABLE
            $notification_table = $db_name . '.app_notification';
            $notification_to_user_table = $db_name . '.app_notification_to_user';
            $app_setting_key_value = $db_name . '.app_setting_key_value';

            //HRM RELATED TABLE
            $staffs_type_table = $db_name . '.hrm_staff';

            $leave_table = $db_name . '.hrm_leaves';
            $leave_type_table = $db_name . '.hrm_leave_type';

            $attendance_type_table = $db_name . '.hrm_staff_attendance';
            $hrm_attendances = $db_name . '.hrm_attendances';
            $designations_type_table = $db_name . '.hrm_designation';
            $departments_type_table = $db_name . '.hrm_department';
            $hrm_education = $db_name . '.hrm_education';
            $hrm_document = $db_name . '.hrm_document';
            $hrm_staff_experience = $db_name . '.hrm_staff_experience';
            $hrm_staff_document = $db_name . '.hrm_staff_document';
            $hrm_staff_bank_details = $db_name . '.hrm_staff_bank_details';
            $hrm_staff_bank_details = $db_name . '.hrm_staff_bank_details';
            $hrm_staff_qualification = $db_name . '.hrm_staff_qualification';
            $hrm_staff_address = $db_name . '.hrm_staff_address';
            $hrm_holidays = $db_name . '.hrm_holidays';
            $hrm_salary = $db_name . '.hrm_salary';

            // //ECOMM RELATED TABLE
            // $category_table = $db_name .'.ecm_categories';
            // $category_description_table = $db_name .'.ecm_categories_description';

            $web_custom_air_shipping = $db_name . '.web_custom_air_shipping';
            $web_custom_sea_shipping = $db_name . '.web_custom_sea_shipping';


            $products_table = $db_name . '.ecm_products';
            $brand_table = $db_name . '.ecm_brands';
            $ecm_coupon = $db_name . '.ecm_coupon';
            $usr_role_has_sections = $db_name . '.usr_role_has_sections';
            $usr_role_section_has_permissions = $db_name . '.usr_role_section_has_permissions';
            $mas_translation_key = $db_name . '.app_translation_key';
            $hrm_staff_remark = $db_name . '.hrm_staff_remark';
            $hrm_staff_remuneration = $db_name . '.hrm_staff_remuneration';
            $ecm_categories = $db_name . '.ecm_categories';
            $ecm_categories_description = $db_name . '.ecm_categories_description';
            $ecm_products = $db_name . '.ecm_products';
            $ecm_products_description = $db_name . '.ecm_products_description';
            $ecm_products_to_categories = $db_name . '.ecm_products_to_categories';
            $ecm_product_feature = $db_name . '.ecm_products_feature';
            $ecm_product_type_fields = $db_name . '.ecm_product_type_fields';
            $ecm_product_type = $db_name . '.ecm_product_type';
            $ecm_fields = $db_name . '.ecm_fields';
            $ecm_fieldsgroup = $db_name . '.ecm_fieldsgroup';
            $ecm_product_type_to_fieldsgroup = $db_name . '.ecm_product_type_to_fieldsgroup';
            $ecm_products_options = $db_name . '.ecm_products_options';
            $ecm_products_options_values = $db_name . '.ecm_products_options_values';
            $ecm_products_type_field_value = $db_name . '.ecm_products_type_field_value';
            $ecm_products_attributes = $db_name . '.ecm_products_attributes';
            $app_images = $db_name . '.app_images';
            $ecm_products_to_images = $db_name . '.ecm_products_to_images';
            $ecm_supplier = $db_name . '.ecm_supplier';
            $web_seometa = $db_name . '.web_seometa';
            $ecm_brand = $db_name . '.ecm_brand';
            $ecm_products_to_supplier = $db_name . '.ecm_products_to_supplier';
            $biz_categories = $db_name . '.biz_categories';
            $biz_categories_description = $db_name . '.biz_categories_description';

            $biz_listing = $db_name . '.biz_listing';
            $biz_listing_description = $db_name . '.biz_listing_description';
            $biz_listing_to_categories = $db_name . '.biz_listing_to_categories';
            $biz_listing_feature = $db_name . '.biz_listing_feature';
            $biz_listing_options = $db_name . '.biz_listing_options';
            $biz_listing_options_values = $db_name . '.biz_listing_options_values';
            $biz_listing_attributes = $db_name . '.biz_listing_attributes';
            $biz_listing_to_images = $db_name . '.biz_listing_to_images';

            $biz_listing_price = $db_name . '.biz_listing_price';

            $biz_featured_group = $db_name . '.biz_featured_group';
            $biz_featured_listing = $db_name . '.biz_featured_listing';


            //Recruit

            $rec_interview_schedule_comments = $db_name . '.rec_interview_schedule_comments';
            $rec_interview_schedules = $db_name . '.rec_interview_schedules';
            $rec_job_alert_categories = $db_name . '.rec_job_alert_categories';
            $rec_job_alert_locations = $db_name . '.rec_job_alert_locations';
            $rec_job_alerts = $db_name . '.rec_job_alerts';
            $rec_job_application_answers = $db_name . '.rec_job_application_answers';
            $rec_job_applications = $db_name . '.rec_job_applications';
            $rec_job_categories = $db_name . '.rec_job_categories';
            $rec_job_job_application = $db_name . '.rec_job_job_application';
            $rec_job_locations = $db_name . '.rec_job_locations';
            $rec_job_offer_questions = $db_name . '.rec_job_offer_questions';
            $rec_job_onboard_questions = $db_name . '.rec_job_onboard_questions';
            $rec_job_questions = $db_name . '.rec_job_questions';
            $rec_job_types = $db_name . '.rec_job_types';
            $rec_jobs = $db_name . '.rec_jobs';
            $rec_skills = $db_name . '.rec_skills';
            $rec_work_experiences = $db_name . '.rec_work_experiences';
            $rec_job_skills = $db_name . '.rec_job_skills';
            $rec_questions = $db_name . '.rec_questions';
            $rec_interview_schedule_employees = $db_name . '.rec_interview_schedule_employees';
            $rec_application_status = $db_name . '.rec_application_status';
            $rec_on_board_details = $db_name . '.rec_on_board_details';
            $rec_documents = $db_name . '.rec_documents';
            $rec_on_board_files = $db_name . '.rec_on_board_files';
            $rec_zoom_settings = $db_name . '.rec_zoom_settings';
            $rec_zoom_meetings = $db_name . '.rec_zoom_meetings';
            $rec_applicant_notes = $db_name . '.rec_applicant_notes';
            $rec_onboard_answers = $db_name . '.rec_onboard_answers';
            $crm_expenses = $db_name . '.crm_expenses';
            $acc_transaction_category = $db_name . '.acc_transaction_category';
            $acc_transaction_head = $db_name . '.acc_transaction_head';
            $acc_invoices = $db_name . '.acc_invoices';
            $acc_invoice_services = $db_name . '.acc_invoice_services';
            $acc_invoice_items = $db_name . '.acc_invoice_items';
            $acc_invoice_item_taxes = $db_name . '.acc_invoice_item_taxes';
            $acc_transactions = $db_name . '.acc_transactions';
            $acc_transaction_remittance = $db_name . '.acc_transaction_remittance';
            $acc_repeating_transactions = $db_name . '.acc_repeating_transactions';
            $xls_sheet = $db_name . '.xls_sheet';
            $xls_sheet_element = $db_name . '.xls_sheet_element';
            $xls_employee = $db_name . '.xls_employee';
            $xls_location = $db_name . '.xls_location';
            $xls_designation = $db_name . '.xls_designation';
            $acc_accounts = $db_name . '.acc_accounts';









            $crm_lead_sett_group = $db_name . '.crm_lead_sett_group';
            $crm_lead_sett_tags = $db_name . '.crm_lead_sett_tags';
            $crm_lead = $db_name . '.crm_lead';
            $crm_lead_to_tags = $db_name . '.crm_lead_to_tags';
            $crm_quote_reminder_history = $db_name . '.crm_quote_reminder_history';
            $crm_lead_followup = $db_name . '.crm_lead_followup';
            $crm_lead_followup_note = $db_name . '.crm_lead_followup_note';
            $crm_lead_sett_source = $db_name . '.crm_lead_sett_source';
            $crm_lead_sett_status = $db_name . '.crm_lead_sett_status';
            $crm_lead_contact = $db_name . '.crm_lead_contact';
            $crm_lead_social_link = $db_name . '.crm_lead_social_link';
            $crm_lead_followup_history = $db_name . '.crm_lead_followup_history';
            $crm_customer = $db_name . '.crm_customer';
            $crm_customer_address = $db_name . '.crm_customer_address';
            $crm_customer_contact = $db_name . '.crm_customer_contact';
            $crm_quotation = $db_name . '.crm_quotation';
            $crm_quotation_item = $db_name . '.crm_quotation_item';
            $crm_setting_payment_terms = $db_name . '.crm_setting_payment_terms';
            $crm_setting_tax = $db_name . '.crm_setting_tax';
            $crm_setting_tax_group = $db_name . '.crm_setting_tax_group';
            $crm_setting_tax_percent = $db_name . '.crm_setting_tax_percent';
            $crm_setting_tax_type = $db_name . '.crm_setting_tax_type';
            $crm_setting_tax_to_tax_group = $db_name . '.crm_setting_tax_to_tax_group';
            $crm_quotation_to_payment_term = $db_name . '.crm_quotation_to_payment_term';
            $ecm_categories_to_options = $db_name . '.ecm_categories_to_options';
            $ecm_categories_to_brands = $db_name . '.ecm_categories_to_brands';
            $web_banner_setting = $db_name . '.web_banner_setting';
            $web_pages = $db_name . '.web_pages';
            $web_pages_description = $db_name . '.web_pages_description';
            $web_banners = $db_name . '.web_banners';
            //   $web_banners_group   = $db_name .'.web_banners_group';
            $web_menu = $db_name . '.web_menu';
            $web_menu_details = $db_name . '.web_menu_details';
            $web_menu_group = $db_name . '.web_menu_group';

            $app_reminder_tracker = $db_name . '.app_reminder_tracker';
            $app_reminder_setting = $db_name . '.app_reminder_setting';





            $web_faq = $db_name . '.web_faq';
            $web_faq_group = $db_name . '.web_faq_group';
            $web_orders = $db_name . '.web_orders';
            $web_order_items = $db_name . '.web_order_items';
            $web_order_address = $db_name . '.web_order_address';
            $web_posts = $db_name . '.web_posts';
            $web_post_descriptions = $db_name . '.web_post_descriptions';
            $web_links = $db_name . '.web_links';
            $web_reviews = $db_name . '.web_reviews';
            $web_enquiry = $db_name . '.web_enquiry';
            $web_enquiry_products = $db_name . '.web_enquiry_products';
            $web_newsletter = $db_name . '.web_newsletter';
            $app_email_group = $db_name . '.app_email_group';
            $app_email_templates = $db_name . '.app_email_templates';

            //   $app_setting_group_key = $db_name .'.app_setting_group_key';
            $app_setting_key_value = $db_name . '.app_setting_key_value';
            //  $app_settings_group = $db_name .'.app_settings_group';
            $web_template_setting = $db_name . '.web_template_setting';
            $web_functions = $db_name . '.web_functions';
            $web_functions_to_industry = $db_name . '.web_functions_to_industry';
            $web_function_settings = $db_name . '.web_function_settings';
            $ecm_featured_group = $db_name . '.ecm_featured_group';
            $ecm_featured_products = $db_name . '.ecm_featured_products';
            $ecm_featured_group_content = $db_name . '.ecm_featured_group_content';
            $web_feature = $db_name . '.web_feature';
            $web_template_component_setting = $db_name . '.web_template_component_setting';
            $web_testimonial = $db_name . '.web_testimonial';
            $app_permission_types = $db_name . '.app_permission_types';
            $ecm_product_feature = $db_name . '.ecm_products_feature';
            $ecm_categories_to_brands = $db_name . '.ecm_categories_to_brands';
            $ecm_products_shipping = $db_name . '.ecm_products_shipping';
            $ecm_products_price = $db_name . '.ecm_products_price';
            $web_setting_key_value = $db_name . '.web_setting_key_value';
            $crm_form = $db_name . '.crm_form';
            $crm_form_fields = $db_name . '.crm_form_fields';
            $crm_form_data = $db_name . '.crm_form_data';
            $crm_lead_sett_agent = $db_name . '.crm_lead_sett_agent';
            $app_email_group = $db_name . '.app_email_group';
            $app_email_templates = $db_name . '.app_email_templates';
            $subscriber_web_template_component_setting = $db_name . '.web_template_component_setting';
            //Language
            $web_post_category = $db_name . '.web_post_category';
            $web_post_category_descriptions = $db_name . '.web_post_category_descriptions';
            $web_post_tags_descriptions = $db_name . '.web_post_tags_descriptions';



            $web_post_to_category = $db_name . '.web_post_to_category';
            $web_post_tags = $db_name . '.web_post_tags';
            $web_post_to_tag = $db_name . '.web_post_to_tag';
            $web_post_author = $db_name . '.web_post_author';
            $sub_plan = $db_name . '.sub_plan';
            $sub_subscription = $db_name . '.sub_subscription';
            $sub_subscription_history = $db_name . '.sub_subscription_history';
            $sub_subscription_transaction = $db_name . '.sub_subscription_transaction';
            $common_plan_feature = $db_name . '.sub_plan_feature';
            $common_plan_to_feature = $db_name . '.sub_plan_to_feature';
            $common_plan_to_price = $db_name . '.sub_plan_pricing';
            $sub_plan_group = $db_name . '.sub_plan_group';
            $crm_tickets = $db_name . '.crm_tickets';
            $crm_ticket_agent_groups = $db_name . '.crm_ticket_agent_groups';
            $crm_ticket_custom_forms = $db_name . '.crm_ticket_custom_forms';
            $crm_ticket_groups = $db_name . '.crm_ticket_groups';
            $crm_ticket_replies = $db_name . '.crm_ticket_replies';
            $crm_ticket_reply_templates = $db_name . '.crm_ticket_reply_templates';
            $crm_ticket_types = $db_name . '.crm_ticket_types';
            $mkt_templates = $db_name . '.mkt_templates';
            $mkt_contacts = $db_name . '.mkt_contacts';
            $mkt_contacts_groups = $db_name . '.mkt_contacts_groups';
            $mkt_contacts_to_groups = $db_name . '.mkt_contacts_to_groups';
            $mkt_campaign_emails = $db_name . '.mkt_campaign_emails';
            $mkt_campaign_tracker = $db_name . '.mkt_campaign_tracker';
            $mkt_campaigns = $db_name . '.mkt_campaigns';
            $mkt_contacts_newsletter = $db_name . '.mkt_contacts_newsletter';
            $mkt_sender_email = $db_name . '.mkt_sender_email';
            $subs_web_template_html = $db_name . '.web_template_html';
            $web_sliders = $db_name . '.web_sliders';
            $web_sliders_images = $db_name . '.web_sliders_images';
            $app_addon_manager = $db_name . '.app_addon_manager';
            $app_addon_setting = $db_name . '.app_addon_setting';
            $web_order_updates = $db_name . '.web_order_updates';
            $web_order_status = $db_name . '.web_order_status';
            $web_order_shipment = $db_name . '.web_order_shipment';
        } else {

            //SUPER ADMIN DATABASE
            $db_name = $dbsuperadmin;

            $rec_jobs = $db_name . '.rec_jobs';

            //USER RELATED TABLE
            $users_table = $db_name . '.usr_users';
            $roles_table = $db_name . '.usr_roles';
            //  $permissions_table = $db_name . '.app_permissions';

            $user_has_roles_table = $db_name . '.usr_user_has_roles';
            $role_has_permissions_table = $db_name . '.usr_role_has_permissions';

            $user_logins_table = $db_name . '.usr_user_logins';
            $hrm_attendances = $db_name . '.hrm_attendances';


            //APP RELATED TABLE
            $notification_table = $db_name . '.app_notification';
            $notification_to_user_table = $db_name . '.app_notification_to_user';
            $app_setting_key_value = $db_name . '.app_setting_key_value';
            $ecm_product_feature = $db_name . '.ecm_product_feature';
            $ecm_categories_to_brands = $db_name . '.ecm_categories_to_brands';
            $web_setting_key_value = $db_name . '.web_setting_key_value';
            $app_email_group = $db_name . '.app_email_group';
            $app_email_templates = $db_name . '.app_email_templates';
            $biz_categories = $db_name . '.biz_categories';
            $biz_categories_description = $db_name . '.biz_categories_description';
            $biz_listing = $db_name . '.biz_listing';
            $biz_listing_description = $db_name . '.biz_listing_description';
            $biz_listing_to_categories = $db_name . '.biz_listing_to_categories';
            $biz_listing_feature = $db_name . '.biz_listing_feature';
            $biz_listing_options = $db_name . '.biz_listing_options';
            $biz_listing_options_values = $db_name . '.biz_listing_options_values';
            $biz_listing_attributes = $db_name . '.biz_listing_attributes';
            $biz_listing_to_images = $db_name . '.biz_listing_to_images';
            $biz_featured_group = $db_name . '.biz_featured_group';
            $biz_featured_listing = $db_name . '.biz_featured_listing';






            $crm_form = $db_name . '.crm_form';
            $crm_form_fields = $db_name . '.crm_form_fields';
            $crm_form_data = $db_name . '.crm_form_data';

            //HRM RELATED TABLE
            $leave_table = $db_name . '.hrm_staff_leave';
            $leave_type_table = $db_name . '.hrm_leave_type';
            $attendance_type_table = $db_name . '.hrm_staff_attendance';
            $hrm_education = $db_name . '.hrm_education';
            $designations_type_table = $db_name . '.hrm_designation';
            $departments_type_table = $db_name . '.hrm_department';
            $hrm_document = $db_name . '.hrm_document';
            $staffs_type_table = $db_name . '.hrm_staff';
            $hrm_staff_experience = $db_name . '.hrm_staff_experience';
            $hrm_staff_document = $db_name . '.hrm_staff_document';
            $hrm_staff_bank_details = $db_name . '.hrm_staff_bank_details';
            $hrm_staff_qualification = $db_name . '.hrm_staff_qualification';
            $hrm_staff_address = $db_name . '.hrm_staff_address';
            $hrm_holidays = $db_name . '.hrm_holidays';
            $hrm_salary = $db_name . '.hrm_salary';
            $web_custom_air_shipping = $db_name . '.web_custom_air_shipping';
            $web_custom_sea_shipping = $db_name . '.web_custom_sea_shipping';


            $ecm_products_shipping = $db_name . '.ecm_products_shipping';
            $usr_role_has_sections = $db_name . '.usr_role_has_sections';
            $usr_role_section_has_permissions = $db_name . '.usr_role_section_has_permissions';
            $mas_translation_key = $db_name . '.app_translation_key';
            $hrm_staff_remark = $db_name . '.hrm_staff_remark';
            $hrm_staff_remuneration = $db_name . '.hrm_staff_remuneration';
            $ecm_categories = $db_name . '.ecm_categories';
            $ecm_categories_description = $db_name . '.ecm_categories_description';
            $ecm_products = $db_name . '.ecm_products';
            $ecm_products_description = $db_name . '.ecm_products_description';
            $ecm_products_to_categories = $db_name . '.ecm_products_to_categories';
            $ecm_product_type = $db_name . '.ecm_product_type';
            $ecm_product_feature = $db_name . '.ecm_product_feature';
            $ecm_product_type_fields = $db_name . '.ecm_product_type_fields';
            $ecm_fields = $db_name . '.ecm_fields';
            $ecm_fieldsgroup = $db_name . '.ecm_fieldsgroup';
            $ecm_product_type_to_fieldsgroup = $db_name . '.ecm_product_type_to_fieldsgroup';
            $ecm_products_options = $db_name . '.ecm_products_options';
            $ecm_products_options_values = $db_name . '.ecm_products_options_values';
            $ecm_products_type_field_value = $db_name . '.ecm_products_type_field_value';
            $ecm_products_attributes = $db_name . '.ecm_products_attributes';
            $app_images = $db_name . '.app_images';
            $ecm_products_to_images = $db_name . '.ecm_products_to_images';
            $ecm_supplier = $db_name . '.ecm_supplier';
            $web_seometa = $db_name . '.web_seometa';
            $ecm_brand = $db_name . '.ecm_brand';
            $ecm_products_to_supplier = $db_name . '.ecm_products_to_supplier';
            $crm_lead_sett_agent = $db_name . '.crm_lead_sett_agent';
            $crm_lead_sett_group = $db_name . '.crm_lead_sett_group';
            $crm_lead_to_tags = $db_name . '.crm_lead_to_tags';
            $crm_lead_sett_tags = $db_name . '.crm_lead_sett_tags';
            $crm_lead = $db_name . '.crm_lead';
            $crm_quote_reminder_history = $db_name . '.crm_quote_reminder_history';
            $crm_lead_followup = $db_name . '.crm_lead_followup';
            $crm_lead_followup_note = $db_name . '.crm_lead_followup_note';
            $crm_lead_sett_source = $db_name . '.crm_lead_sett_source';
            $crm_lead_sett_status = $db_name . '.crm_lead_sett_status';
            $crm_lead_contact = $db_name . '.crm_lead_contact';
            $crm_lead_social_link = $db_name . '.crm_lead_social_link';
            $crm_lead_followup_history = $db_name . '.crm_lead_followup_history';
            $crm_customer = $db_name . '.crm_customer';
            $crm_customer_address = $db_name . '.crm_customer_address';
            $crm_customer_contact = $db_name . '.crm_customer_contact';
            $crm_quotation = $db_name . '.crm_quotation';
            $crm_quotation_item = $db_name . '.crm_quotation_item';


            $crm_setting_payment_terms = $db_name . '.crm_setting_payment_terms';
            // $crm_setting_tax = $db_name . '.crm_setting_tax';
            $crm_setting_tax_group = $db_name . '.crm_setting_tax_group';
            $crm_setting_tax_to_tax_group = $db_name . '.crm_setting_tax_to_tax_group';
            $crm_setting_tax_percent = $db_name . '.crm_setting_tax_percent';
            $crm_setting_tax_type = $db_name . '.crm_setting_tax_type';
            $crm_quotation_to_payment_term = $db_name . '.crm_quotation_to_payment_term';
            $ecm_categories_to_options = $db_name . '.ecm_categories_to_options';

            $ecm_categories_to_brands = $db_name . '.ecm_categories_to_brands';

            $web_banner_setting = $db_name . '.web_banner_setting';

            $web_pages = $db_name . '.web_pages';
            $web_pages_description = $db_name . '.web_pages_description';

            $web_banners = $db_name . '.web_banners';
            //   $web_banners_group   = $db_name .'.web_banners_group';
            $web_menu = $db_name . '.web_menu';
            $web_menu_details = $db_name . '.web_menu_details';
            $web_menu_group = $db_name . '.web_menu_group';


            $web_orders = $db_name . '.web_orders';
            $web_order_items = $db_name . '.web_order_items';
            $web_order_address = $db_name . '.web_order_address';
            $web_posts = $db_name . '.web_posts';
            $web_post_descriptions = $db_name . '.web_post_descriptions';
            $web_post_category = $db_name . '.web_post_category';
            $web_post_category_descriptions = $db_name . '.web_post_category_descriptions';
            $web_post_tags_descriptions = $db_name . '.web_post_tags_descriptions';

            $web_post_to_category = $db_name . '.web_post_to_category';
            $web_post_tags = $db_name . '.web_post_tags';
            $web_post_to_tag = $db_name . '.web_post_to_tag';
            $web_post_author = $db_name . '.web_post_author';

            $web_links = $db_name . '.web_links';

            $web_reviews = $db_name . '.web_reviews';
            $web_enquiry = $db_name . '.web_enquiry';
            $web_enquiry_products = $db_name . '.web_enquiry_products';
            $web_newsletter = $db_name . '.web_newsletter';

            $app_email_group = $db_name . '.app_email_group';
            $app_email_templates = $db_name . '.app_email_templates';

            $app_setting_key_value = $db_name . '.app_setting_key_value';
            //   $app_settings_group =  $db_name.'.app_settings_group';
            $web_template_setting = $db_name . '.web_template_setting';

            $web_functions = $db_name . '.web_functions';
            $web_functions_to_industry = $db_name . '.web_functions_to_industry';

            $web_function_settings = $db_name . '.web_function_settings';

            $ecm_featured_group = $db_name . '.ecm_featured_group';
            $ecm_featured_products = $db_name . '.ecm_featured_products';
            $web_feature = $db_name . '.web_feature';




            $subs_web_template_html = $db_name . '.web_template_html';

            $web_template_component_setting = $db_name . '.web_template_component_setting';
            $web_testimonial = $db_name . '.web_testimonial';
            $app_permission_types = $db_name . '.app_permission_types';
            $ecm_products_price = $db_name . '.ecm_products_price';
            $subscriber_web_template_component_setting = $db_name . '.web_template_component_setting';

            // $mas_shipping_method  = $db_name .'.mas_shipping_method';

            // $mas_shipping_method_key  = $db_name .'.mas_shipping_method_key';

            //order status
            // $web_order_updates = $db_name . '.web_order_updates';
            // $web_order_status = $db_name . '.web_order_status';
            // $web_order_shipment = $db_name . '.web_order_shipment';
        }

        //Global start
        $landing_users = $dblanding . '.users';
        $landing_web_pages = $dblanding . '.web_pages';
        $landing_web_pages_description = $dblanding . '.web_pages_description';
        $landing_web_testimonial = $dblanding . '.web_testimonial';
        $landing_web_enquiry = $dblanding . '.web_enquiry';
        $landing_web_faq = $dblanding . '.web_faq';
        $landing_web_faq_group = $dblanding . '.web_faq_group';
        $landing_web_posts = $dblanding . '.web_posts';
        $landing_web_post_descriptions = $dblanding . '.web_post_descriptions';
        $landing_web_seometa = $dblanding . '.web_seometa';
        $super_web_post_category = $dblanding . '.web_post_category';
        $super_web_post_to_category = $dblanding . '.web_post_to_category';
        $super_web_post_tags = $dblanding . '.web_post_tags';
        $super_web_post_to_tag = $dblanding . '.web_post_to_tag';
        $super_web_post_author = $dblanding . '.web_post_author';
        $landing_web_menu = $dblanding . '.web_menu';
        $landing_web_menu_details = $dblanding . '.web_menu_details';
        $landing_web_menu_group = $dblanding . '.web_menu_group';
        $landing_crm_form = $dblanding . '.crm_form';
        $landing_crm_form_data = $dblanding . '.crm_form_data';
        $landing_crm_form_fields = $dblanding . '.crm_form_fields';
        $landing_app_images = $dblanding . '.app_images';
        $landing_web_links = $dblanding . '.web_links';
        $landing_crm_agent = $dblanding . '.crm_lead_sett_agent';
        $landing_crm_tags = $dblanding . '.crm_lead_sett_tags';
        $landing_crm_lead_to_tags = $dblanding . '.crm_lead_to_tags';
        $landing_crm_industry = $dblanding . '.crm_industry';
        $landing_crm_lead = $dblanding . '.crm_lead';
        $landing_crm_lead_contact = $dblanding . '.crm_lead_contact';
        $landing_crm_lead_followup = $dblanding . '.crm_lead_followup';
        $landing_crm_lead_followup_history = $dblanding . '.crm_lead_followup_history';
        $landing_crm_lead_social_link = $dblanding . '.crm_lead_social_link';
        $landing_crm_notes = $dblanding . '.crm_lead_followup_note';
        $landing_crm_lead_source = $dblanding . '.crm_lead_sett_source';
        $landing_crm_lead_social_link = $dblanding . '.crm_lead_social_link';
        $landing_crm_lead_status = $dblanding . '.crm_lead_sett_status';
        $landing_app_reminder_setting = $dblanding . '.app_reminder_setting';
        $landing_app_reminder_tracker = $dblanding . '.app_reminder_tracker';

        $landing_coupon = $dblanding . '.sub_coupon';
        $landing_sub_plan = $dblanding . '.sub_plan';
        $landing_sub_plan_feature = $dblanding . '.sub_plan_feature';
        $landing_sub_plan_group = $dblanding . '.sub_plan_group';
        $landing_sub_plan_pricing = $dblanding . '.sub_plan_pricing';
        $landing_sub_plan_to_feature = $dblanding . '.sub_plan_to_feature';

        //super admin
        $super_table = $dbsuperadmin;

        $web_functions = $super_table . '.web_functions';
        $mas_languages_table = $super_table . '.mas_languages';
        $mas_locales = $super_table . '.mas_locales';

        $mas_translation_table = $super_table . '.app_translation';
        $mas_countries_table = $super_table . '.mas_countries';
        $mas_currencies_table = $super_table . '.mas_currencies';
        // $hrm_attendances = $super_table . '.hrm_attendances';

        $app_permission_types = $super_table . '.app_permission_types';
        $permissions_table = $super_table . '.app_permissions';
        $super_app_images = $super_table . '.app_images';
        $app_db_update_log = $super_table . '.app_db_update_log';

        $super_crm_form = $super_table . '.crm_form';
        $super_crm_form_fields = $super_table . '.crm_form_fields';


        $super_app_email_group = $super_table . '.app_email_group';
        $super_app_email_templates = $super_table . '.app_email_templates';

        $sub_subscription_history_table = $super_table . '.sub_subscription_history';
        $sub_subscription_transaction_table = $super_table . '.sub_subscription_transaction';
        $sub_subscription_to_user_table = $super_table . '.sub_subscription_to_user';
        $sub_plan_pricing = $super_table . '.sub_plan_pricing';
        $sub_plan_table = $super_table . '.sub_plan';

        $sub_addon_plan = $super_table . '.sub_addon_plan';
        $app_cron_schedular = $super_table . '.app_cron_schedular';
        $sub_addon_plan_pricing = $super_table . '.sub_addon_plan_pricing';
        $sub_addon_plan_to_feature = $super_table . '.sub_addon_plan_to_feature';
        $sub_addon_plan_feature = $super_table . '.sub_addon_plan_feature';
        $common_app_setting_key_value = $super_table . '.app_setting_key_value';
        $sub_plan_to_industry_table = $super_table . '.sub_plan_to_industry';
        $sub_subscription_table = $super_table . '.sub_subscription';
        $mas_countries_timezone_table = $super_table . '.mas_countries_timezone';
        $sub_transaction_table = $super_table . '.sub_transaction';
        $app_module_table = $super_table . '.app_module';
        $app_module_section_table = $super_table . '.app_module_section';
        $sub_users_to_business_table = $super_table . '.sub_users_to_business';
        $sub_business_user_table = $super_table . '.sub_business_user';
        $app_industry_table = $super_table . '.app_industry';
        $app_industry_category = $super_table . '.app_industry_category';
        $app_industry_has_module_table = $super_table . '.app_industry_has_module';
        $sub_subscription_to_module = $super_table . '.sub_subscription_to_module';
        $mas_payment_method_table = $super_table . '.mas_payment_method';
        $mas_payment_method_details_table = $super_table . '.mas_payment_method_key';
        $sub_business_info_table = $super_table . '.sub_business_info';
        $sub_business_info_addl_table = $super_table . '.sub_business_info_addl';
        $ecm_product_type = $super_table . '.ecm_product_type';
        $ecm_fields = $super_table . '.ecm_fields';
        $ecm_fieldsgroup = $super_table . '.ecm_fieldsgroup';
        $ecm_product_type_to_fieldsgroup = $super_table . '.ecm_product_type_to_fieldsgroup';
        $web_banner_position = $super_table . '.web_banner_position';
        //      $web_menu_group = $super_table . '.web_menu_group';
        $app_settings_group = $super_table . '.app_settings_group';
        $app_setting_group_key = $super_table . '.app_setting_group_key';
        $web_template_component_setting = $super_table . '.web_template_component_setting';
        $web_templates = $super_table . '.web_templates';
        $web_template_section_options = $super_table . '.web_template_section_options';
        $web_template_sections = $super_table . '.web_template_sections';
        $web_template_component = $super_table . '.web_template_component';
        $web_banners_group = $super_table . '.web_banners_group';

        // $mas_shipping_method  = $super_table .'.mas_shipping_method';
        // $mas_shipping_method_key  = $super_table .'.mas_shipping_method_key';

        // $web_template_component_setting  = $db_name .'.web_template_component_setting';

        $web_settings_group_key = $super_table . '.web_settings_group_key';
        $web_settings_group = $super_table . '.web_settings_group';
        // $ecm_images = 'support_subs_0001.ecm_images'; 

        $sub_plan_feature = $super_table . '.sub_plan_feature';
        $sub_plan_to_feature = $super_table . '.sub_plan_to_feature';

        //seo table
        $seo_assigned_worker = $super_table . '.seo_assigned_worker';
        $seo_assigned_worker_task = $super_table . '.seo_assigned_worker_task';
        $seo_monthly_strategy = $super_table . '.seo_monthly_strategy';
        $seo_settings_result_title = $super_table . '.seo_settings_result_title';
        $seo_settings_task = $super_table . '.seo_settings_task';
        $seo_submission_websites = $super_table . '.seo_submission_websites';
        $seo_website_result = $super_table . '.seo_website_result';
        $seo_websites = $super_table . '.seo_websites';
        $seo_work_report = $super_table . '.seo_work_report';
        $seo_website_keyword = $super_table . '.seo_website_keyword';
        $seo_website_ranking = $super_table . '.seo_website_ranking';

        //end



        $ecm_pc_categories = $super_table . '.ecm_pc_categories';
        $ecm_pc_categories_description = $super_table . '.ecm_pc_categories_description';
        $sup_crm_tickets = $super_table . '.crm_tickets';
        $sup_crm_ticket_agent_groups = $super_table . '.crm_ticket_agent_groups';
        $sup_crm_ticket_custom_forms = $super_table . '.crm_ticket_custom_forms';
        $sup_crm_ticket_groups = $super_table . '.crm_ticket_groups';
        $sup_crm_ticket_replies = $super_table . '.crm_ticket_replies';
        $sup_crm_ticket_reply_templates = $super_table . '.crm_ticket_reply_templates';
        $sup_crm_ticket_types = $super_table . '.crm_ticket_types';
        $sub_plan_feature_to_industry = $super_table . '.sub_plan_feature_to_industry';
        $web_template_html = $super_table . '.web_template_html';
        $web_redirect = $super_table . '.web_redirect';
        $app_kb_articles = $super_table . '.app_kb_articles';
        $app_kb_categories = $super_table . '.app_kb_categories';


        //Global end

        //Config Variable start
        if (isset($db_id) && $db_id > 0) {

            config(['dbtable.db_name' => $db_name]);
            config(['dbtable.common_leave' => $leave_table]);
            config(['dbtable.common_leave_type' => $leave_type_table]);
            config(['dbtable.common_attendance' => $attendance_type_table]);
            config(['dbtable.ecm_products_to_images' => $ecm_products_to_images]);
            config(['dbtable.ecm_supplier' => $ecm_supplier]);
            config(['dbtable.web_seometa' => $web_seometa]);
            config(['dbtable.app_addon_manager' => $app_addon_manager]);
            config(['dbtable.app_addon_setting' => $app_addon_setting]);
            config(['dbtable.ecm_brand' => $ecm_brand]);
            config(['dbtable.ecm_coupon' => $ecm_coupon]);
            config(['dbtable.mkt_contacts' => $mkt_contacts]);
            config(['dbtable.mkt_contacts_groups' => $mkt_contacts_groups]);
            config(['dbtable.mkt_contacts_to_groups' => $mkt_contacts_to_groups]);
            config(['dbtable.mkt_templates' => $mkt_templates]);
            config(['dbtable.web_faq' => $web_faq]);
            config(['dbtable.web_faq_group' => $web_faq_group]);
            // config(['dbtable.hrm_attendances' => $hrm_attendances]);
            config(['dbtable.web_sliders' => $web_sliders]);
            config(['dbtable.web_sliders_images' => $web_sliders_images]);
            config(['dbtable.app_reminder_tracker' => $app_reminder_tracker]);
            config(['dbtable.app_reminder_setting' => $app_reminder_setting]);


            //order status 
            config(['dbtable.web_order_shipment' => $web_order_shipment]);
            config(['dbtable.web_order_status' => $web_order_status]);
            config(['dbtable.web_order_updates' => $web_order_updates]);
            config(['dbtable.mkt_campaign_emails' => $mkt_campaign_emails]);
            config(['dbtable.mkt_campaign_tracker' => $mkt_campaign_tracker]);
            config(['dbtable.mkt_campaigns' => $mkt_campaigns]);
            config(['dbtable.mkt_contacts_newsletter' => $mkt_contacts_newsletter]);
            config(['dbtable.mkt_sender_email' => $mkt_sender_email]);
            config(['dbtable.ecm_products_to_supplier' => $ecm_products_to_supplier]);
            config(['dbtable.ecm_product_feature' => $ecm_product_feature]);
            config(['dbtable.ecm_categories_to_brands' => $ecm_categories_to_brands]);
            config(['dbtable.web_setting_key_value' => $web_setting_key_value]);
            config(['dbtable.sub_plan' => $sub_plan]);
            config(['dbtable.sub_plan_group' => $sub_plan_group]);
            config(['dbtable.sub_subscription' => $sub_subscription]);
            config(['dbtable.sub_subscription_history' => $sub_subscription_history]);
            config(['dbtable.sub_subscription_transaction' => $sub_subscription_transaction]);
            config(['dbtable.biz_categories' => $biz_categories]);
            config(['dbtable.biz_categories_description' => $biz_categories_description]);
            config(['dbtable.biz_listing' => $biz_listing]);
            config(['dbtable.biz_listing_description' => $biz_listing_description]);
            config(['dbtable.biz_listing_feature' => $biz_listing_feature]);
            config(['dbtable.biz_listing_to_categories' => $biz_listing_to_categories]);
            config(['dbtable.biz_listing_options' => $biz_listing_options]);
            config(['dbtable.biz_listing_options_values' => $biz_listing_options_values]);
            config(['dbtable.biz_listing_attributes' => $biz_listing_attributes]);
            config(['dbtable.biz_listing_to_images' => $biz_listing_to_images]);
            config(['dbtable.biz_listing_price' => $biz_listing_price]);
            config(['dbtable.biz_featured_group' => $biz_featured_group]);
            config(['dbtable.biz_featured_listing' => $biz_featured_listing]);
            config(['dbtable.crm_form' => $crm_form]);
            config(['dbtable.crm_form_fields' => $crm_form_fields]);
            config(['dbtable.crm_form_data' => $crm_form_data]);
            config(['dbtable.web_banner_setting' => $web_banner_setting]);
            config(['dbtable.web_testimonial' => $web_testimonial]);
            config(['dbtable.ecm_featured_group' => $ecm_featured_group]);
            config(['dbtable.ecm_featured_products' => $ecm_featured_products]);
            config(['dbtable.ecm_featured_group_content' => $ecm_featured_group_content]);
            config(['dbtable.web_feature' => $web_feature]);
            config(['dbtable.web_banner_position' => $web_banner_position]);
            config(['dbtable.web_pages' => $web_pages]);
            config(['dbtable.web_pages_description' => $web_pages_description]);
            config(['dbtable.web_enquiry' => $web_enquiry]);
            config(['dbtable.web_enquiry_products' => $web_enquiry_products]);
            config(['dbtable.web_posts' => $web_posts]);
            config(['dbtable.web_post_descriptions' => $web_post_descriptions]);
            config(['dbtable.web_post_category' => $web_post_category]);
            config(['dbtable.web_post_category_descriptions' => $web_post_category_descriptions]);
            config(['dbtable.web_post_tags_descriptions' => $web_post_tags_descriptions]);
            config(['dbtable.web_post_to_category' => $web_post_to_category]);
            config(['dbtable.web_post_tags' => $web_post_tags]);
            config(['dbtable.web_post_to_tag' => $web_post_to_tag]);
            config(['dbtable.web_post_author' => $web_post_author]);
            config(['dbtable.web_links' => $web_links]);


            //crm
            config(['dbtable.crm_lead_sett_agent' => $crm_lead_sett_agent]);
            config(['dbtable.crm_tickets' => $crm_tickets]);
            config(['dbtable.crm_ticket_agent_groups' => $crm_ticket_agent_groups]);
            config(['dbtable.crm_ticket_custom_forms' => $crm_ticket_custom_forms]);
            config(['dbtable.crm_ticket_groups' => $crm_ticket_groups]);
            config(['dbtable.crm_ticket_replies' => $crm_ticket_replies]);
            config(['dbtable.crm_ticket_reply_templates' => $crm_ticket_reply_templates]);
            config(['dbtable.crm_ticket_types' => $crm_ticket_types]);

            //recruit
            config(['dbtable.rec_interview_schedule_comments' => $rec_interview_schedule_comments]);
            config(['dbtable.rec_interview_schedules' => $rec_interview_schedules]);
            config(['dbtable.rec_job_alert_categories' => $rec_job_alert_categories]);
            config(['dbtable.rec_job_alert_locations' => $rec_job_alert_locations]);
            config(['dbtable.rec_job_alerts' => $rec_job_alerts]);
            config(['dbtable.rec_job_application_answers' => $rec_job_application_answers]);
            config(['dbtable.rec_job_applications' => $rec_job_applications]);
            config(['dbtable.rec_job_categories' => $rec_job_categories]);
            config(['dbtable.rec_job_job_application' => $rec_job_job_application]);
            config(['dbtable.rec_job_locations' => $rec_job_locations]);
            config(['dbtable.rec_job_offer_questions' => $rec_job_offer_questions]);
            config(['dbtable.rec_job_onboard_questions' => $rec_job_onboard_questions]);
            config(['dbtable.rec_job_questions' => $rec_job_questions]);
            config(['dbtable.rec_job_types' => $rec_job_types]);
            config(['dbtable.rec_jobs' => $rec_jobs]);
            config(['dbtable.rec_skills' => $rec_skills]);
            config(['dbtable.rec_work_experiences' => $rec_work_experiences]);
            config(['dbtable.rec_job_skills' => $rec_job_skills]);
            config(['dbtable.rec_questions' => $rec_questions]);
            config(['dbtable.rec_interview_schedule_employees' => $rec_interview_schedule_employees]);
            config(['dbtable.rec_application_status' => $rec_application_status]);
            config(['dbtable.rec_on_board_details' => $rec_on_board_details]);
            config(['dbtable.rec_documents' => $rec_documents]);
            config(['dbtable.rec_on_board_files' => $rec_on_board_files]);
            config(['dbtable.rec_zoom_settings' => $rec_zoom_settings]);
            config(['dbtable.rec_zoom_meetings' => $rec_zoom_meetings]);
            config(['dbtable.rec_applicant_notes' => $rec_applicant_notes]);
            config(['dbtable.rec_onboard_answers' => $rec_onboard_answers]);
            config(['dbtable.crm_expenses' => $crm_expenses]);
            config(['dbtable.acc_transaction_category' => $acc_transaction_category]);
            config(['dbtable.acc_transaction_head' => $acc_transaction_head]);
            config(['dbtable.acc_invoices' => $acc_invoices]);
            config(['dbtable.acc_invoice_services' => $acc_invoice_services]);
            config(['dbtable.acc_invoice_items' => $acc_invoice_items]);
            config(['dbtable.acc_invoice_item_taxes' => $acc_invoice_item_taxes]);
            config(['dbtable.acc_transactions' => $acc_transactions]);
            config(['dbtable.acc_transaction_remittance' => $acc_transaction_remittance]);
            config(['dbtable.acc_repeating_transactions' => $acc_repeating_transactions]);
            config(['dbtable.xls_sheet_element' => $xls_sheet_element]);
            config(['dbtable.xls_sheet' => $xls_sheet]);
            config(['dbtable.xls_employee' => $xls_employee]);
            config(['dbtable.xls_location' => $xls_location]);
            config(['dbtable.xls_designation' => $xls_designation]);
            config(['dbtable.acc_accounts' => $acc_accounts]);
            config(['dbtable.common_plan_feature' => $common_plan_feature]);
            config(['dbtable.common_plan_to_feature' => $common_plan_to_feature]);
            config(['dbtable.common_plan_to_price' => $common_plan_to_price]);
        }

        config(['dbtable.common_users' => $users_table]);
        config(['dbtable.common_roles' => $roles_table]);
        config(['dbtable.common_permissions' => $permissions_table]);
        config(['dbtable.common_user_has_roles' => $user_has_roles_table]);
        config(['dbtable.common_role_has_permissions' => $role_has_permissions_table]);
        config(['dbtable.app_permission_types' => $app_permission_types]);
        config(['dbtable.common_user_logins' => $user_logins_table]);
        config(['dbtable.common_notification' => $notification_table]);
        config(['dbtable.common_notification_to_user' => $notification_to_user_table]);
        config(['dbtable.app_images' => $app_images]);
        config(['dbtable.super_app_images' => $super_app_images]);
        config(['dbtable.super_app_email_templates' => $super_app_email_templates]);
        config(['dbtable.common_hrm_attendances' => $hrm_attendances]);
        config(['dbtable.super_app_email_group' => $super_app_email_group]);
        config(['dbtable.subs_web_template_html' => $subs_web_template_html]);
        config(['dbtable.web_template_html' => $web_template_html]);
        config(['dbtable.common_app_setting_key_value' => $common_app_setting_key_value]);
        config(['dbtable.sub_plan_feature_to_industry' => $sub_plan_feature_to_industry]);
        config(['dbtable.app_setting_key_value' => $app_setting_key_value]);
        config(['dbtable.web_function_settings' => $web_function_settings]);
        config(['dbtable.app_settings_group' => $app_settings_group]);
        config(['dbtable.app_setting_group_key' => $app_setting_group_key]);
        config(['dbtable.web_templates' => $web_templates]);
        config(['dbtable.web_template_section_options' => $web_template_section_options]);
        config(['dbtable.web_template_sections' => $web_template_sections]);
        config(['dbtable.web_template_setting' => $web_template_setting]);
        config(['dbtable.web_template_component' => $web_template_component]);
        config(['dbtable.web_template_component_setting' => $web_template_component_setting]);
        config(['dbtable.subscriber_web_template_component_setting' => $subscriber_web_template_component_setting]);
        config(['dbtable.web_functions_to_industry' => $web_functions_to_industry]);

        config(['dbtable.web_functions' => $web_functions]);

        config(['dbtable.web_post_category' => $web_post_category]);
        config(['dbtable.web_post_category_descriptions' => $web_post_category_descriptions]);
        config(['dbtable.web_post_tags_descriptions' => $web_post_tags_descriptions]);

        config(['dbtable.web_post_to_category' => $web_post_to_category]);
        config(['dbtable.web_post_tags' => $web_post_tags]);
        config(['dbtable.web_post_to_tag' => $web_post_to_tag]);
        config(['dbtable.web_post_author' => $web_post_author]);



        config(['dbtable.web_menu_group' => $web_menu_group]);
        config(['dbtable.web_menu' => $web_menu]);
        config(['dbtable.web_menu_details' => $web_menu_details]);

        config(['dbtable.app_email_group' => $app_email_group]);
        config(['dbtable.app_email_templates' => $app_email_templates]);

        config(['dbtable.crm_lead_sett_tags' => $crm_lead_sett_tags]);
        config(['dbtable.crm_lead_sett_group' => $crm_lead_sett_group]);
        config(['dbtable.crm_lead_to_tags' => $crm_lead_to_tags]);
        config(['dbtable.crm_lead' => $crm_lead]);
        config(['dbtable.crm_quote_reminder_history' => $crm_quote_reminder_history]);
        config(['dbtable.crm_lead_followup' => $crm_lead_followup]);
        config(['dbtable.crm_lead_followup_note' => $crm_lead_followup_note]);
        config(['dbtable.crm_lead_sett_source' => $crm_lead_sett_source]);
        config(['dbtable.crm_lead_sett_status' => $crm_lead_sett_status]);
        config(['dbtable.crm_lead_contact' => $crm_lead_contact]);
        config(['dbtable.crm_lead_social_link' => $crm_lead_social_link]);
        config(['dbtable.crm_lead_followup_history' => $crm_lead_followup_history]);
        config(['dbtable.ecm_categories_to_options' => $ecm_categories_to_options]);
        config(['dbtable.ecm_categories_to_brands' => $ecm_categories_to_brands]);

        config(['dbtable.crm_customer' => $crm_customer]);
        config(['dbtable.crm_customer_address' => $crm_customer_address]);
        config(['dbtable.crm_customer_contact' => $crm_customer_contact]);

        config(['dbtable.web_banners' => $web_banners]);
        config(['dbtable.web_banners_group' => $web_banners_group]);

        config(['dbtable.crm_quotation' => $crm_quotation]);
        config(['dbtable.crm_quotation_item' => $crm_quotation_item]);
        config(['dbtable.web_orders' => $web_orders]);
        config(['dbtable.web_order_items' => $web_order_items]);
        config(['dbtable.web_order_address' => $web_order_address]);



        config(['dbtable.crm_setting_payment_terms' => $crm_setting_payment_terms]);
        // config(['dbtable.crm_setting_tax' => $crm_setting_tax]);
        config(['dbtable.crm_setting_tax_group' => $crm_setting_tax_group]);
        config(['dbtable.crm_setting_tax_to_tax_group' => $crm_setting_tax_to_tax_group]);


        config(['dbtable.crm_setting_tax_percent' => $crm_setting_tax_percent]);

        config(['dbtable.crm_setting_tax_type' => $crm_setting_tax_type]);

        config(['dbtable.crm_quotation_to_payment_term' => $crm_quotation_to_payment_term]);

        config(['dbtable.web_settings_group_key' => $web_settings_group_key]);
        config(['dbtable.web_settings_group' => $web_settings_group]);

        config(['dbtable.common_designations' => $designations_type_table]);
        config(['dbtable.common_departments' => $departments_type_table]);
        config(['dbtable.common_staffs' => $staffs_type_table]);
        config(['dbtable.hrm_education' => $hrm_education]);
        config(['dbtable.hrm_document' => $hrm_document]);
        config(['dbtable.hrm_staff_experience' => $hrm_staff_experience]);
        config(['dbtable.hrm_staff_document' => $hrm_staff_document]);
        config(['dbtable.hrm_staff_bank_details' => $hrm_staff_bank_details]);
        config(['dbtable.hrm_staff_qualification' => $hrm_staff_qualification]);
        config(['dbtable.hrm_staff_address' => $hrm_staff_address]);
        config(['dbtable.hrm_holidays' => $hrm_holidays]);
        config(['dbtable.hrm_salary' => $hrm_salary]);
        config(['dbtable.web_custom_air_shipping' => $web_custom_air_shipping]);
        config(['dbtable.web_custom_sea_shipping' => $web_custom_sea_shipping]);




        config(['dbtable.mas_translation_key' => $mas_translation_key]);
        config(['dbtable.hrm_staff_remark' => $hrm_staff_remark]);
        config(['dbtable.hrm_staff_remuneration' => $hrm_staff_remuneration]);
        config(['dbtable.ecm_categories' => $ecm_categories]);
        config(['dbtable.ecm_categories_description' => $ecm_categories_description]);
        config(['dbtable.ecm_products' => $ecm_products]);
        config(['dbtable.ecm_products_description' => $ecm_products_description]);
        config(['dbtable.ecm_products_to_categories' => $ecm_products_to_categories]);
        config(['dbtable.ecm_product_type' => $ecm_product_type]);
        config(['dbtable.ecm_product_feature' => $ecm_product_feature]);
        config(['dbtable.ecm_product_type_fields' => $ecm_product_type_fields]);
        config(['dbtable.ecm_fields' => $ecm_fields]);
        config(['dbtable.ecm_fieldsgroup' => $ecm_fieldsgroup]);
        config(['dbtable.ecm_product_type_to_fieldsgroup' => $ecm_product_type_to_fieldsgroup]);
        config(['dbtable.sub_plan_feature' => $sub_plan_feature]);
        config(['dbtable.sub_plan_to_feature' => $sub_plan_to_feature]);
        config(['dbtable.super_web_post_category' => $super_web_post_category]);
        config(['dbtable.super_web_post_to_category' => $super_web_post_to_category]);
        config(['dbtable.super_web_post_tags' => $super_web_post_tags]);
        config(['dbtable.super_web_post_to_tag' => $super_web_post_to_tag]);
        config(['dbtable.super_web_post_author' => $super_web_post_author]);
        config(['dbtable.app_db_update_log' => $app_db_update_log]);
        config(['dbtable.super_crm_form' => $super_crm_form]);
        config(['dbtable.super_crm_form_fields' => $super_crm_form_fields]);
        config(['dbtable.sup_crm_tickets' => $sup_crm_tickets]);
        config(['dbtable.sup_crm_ticket_agent_groups' => $sup_crm_ticket_agent_groups]);
        config(['dbtable.sup_crm_ticket_custom_forms' => $sup_crm_ticket_custom_forms]);
        config(['dbtable.sup_crm_ticket_groups' => $sup_crm_ticket_groups]);
        config(['dbtable.sup_crm_ticket_replies' => $sup_crm_ticket_replies]);
        config(['dbtable.sup_crm_ticket_reply_templates' => $sup_crm_ticket_reply_templates]);
        config(['dbtable.sup_crm_ticket_types' => $sup_crm_ticket_types]);
        config(['dbtable.web_redirect' => $web_redirect]);
        config(['dbtable.app_kb_articles' => $app_kb_articles]);
        config(['dbtable.app_kb_categories' => $app_kb_categories]);
        config(['dbtable.ecm_products_options' => $ecm_products_options]);
        config(['dbtable.ecm_products_options_values' => $ecm_products_options_values]);
        config(['dbtable.ecm_products_type_field_value' => $ecm_products_type_field_value]);
        config(['dbtable.ecm_products_attributes' => $ecm_products_attributes]);
        config(['dbtable.web_reviews' => $web_reviews]);
        config(['dbtable.web_newsletter' => $web_newsletter]);
        config(['dbtable.ecm_products_shipping' => $ecm_products_shipping]);
        config(['dbtable.ecm_products_price' => $ecm_products_price]);
        config(['dbtable.common_mas_languages' => $mas_languages_table]);
        config(['dbtable.mas_locales' => $mas_locales]);
        config(['dbtable.common_mas_translation' => $mas_translation_table]);
        config(['dbtable.common_mas_countries' => $mas_countries_table]);
        config(['dbtable.common_mas_currencies' => $mas_currencies_table]);
        config(['dbtable.common_sub_subscription_history' => $sub_subscription_history_table]);
        config(['dbtable.common_sub_subscription_transaction' => $sub_subscription_transaction_table]);
        config(['dbtable.common_sub_subscription_to_user_table' => $sub_subscription_to_user_table]);
        config(['dbtable.common_sub_plan' => $sub_plan_table]);
        config(['dbtable.sub_plan_pricing' => $sub_plan_pricing]);
        config(['dbtable.common_sub_subscription' => $sub_subscription_table]);
        config(['dbtable.common_mas_countries_timezone' => $mas_countries_timezone_table]);
        config(['dbtable.common_sub_transaction' => $sub_transaction_table]);
        config(['dbtable.common_app_module' => $app_module_table]);
        config(['dbtable.common_app_module_section' => $app_module_section_table]);
        config(['dbtable.common_sub_users_to_business' => $sub_users_to_business_table]);
        config(['dbtable.common_sub_business_user' => $sub_business_user_table]);
        config(['dbtable.common_app_industry' => $app_industry_table]);
        config(['dbtable.app_industry_category' => $app_industry_category]);
        config(['dbtable.usr_role_has_sections' => $usr_role_has_sections]);
        config(['dbtable.usr_role_section_has_permissions' => $usr_role_section_has_permissions]);
        config(['dbtable.common_app_industry_has_module' => $app_industry_has_module_table]);
        config(['dbtable.sub_subscription_to_module' => $sub_subscription_to_module]);
        config(['dbtable.common_mas_payment_method' => $mas_payment_method_table]);
        config(['dbtable.common_mas_payment_method_details' => $mas_payment_method_details_table]);
        config(['dbtable.common_sub_plan_to_industry' => $sub_plan_to_industry_table]);
        config(['dbtable.common_sub_business_info' => $sub_business_info_table]);
        config(['dbtable.common_sub_business_info_addl' => $sub_business_info_addl_table]);
        config(['dbtable.ecm_pc_categories' => $ecm_pc_categories]);
        config(['dbtable.ecm_pc_categories_description' => $ecm_pc_categories_description]);
        config(['dbtable.sub_addon_plan' => $sub_addon_plan]);
        config(['dbtable.app_cron_schedular' => $app_cron_schedular]);
        config(['dbtable.sub_addon_plan_pricing' => $sub_addon_plan_pricing]);
        config(['dbtable.sub_addon_plan_to_feature' => $sub_addon_plan_to_feature]);
        config(['dbtable.sub_addon_plan_feature' => $sub_addon_plan_feature]);

        //seo dbtable
        config(['dbtable.seo_assigned_worker' => $seo_assigned_worker]);
        config(['dbtable.seo_assigned_worker_task' => $seo_assigned_worker_task]);
        config(['dbtable.seo_monthly_strategy' => $seo_monthly_strategy]);
        config(['dbtable.seo_settings_result_title' => $seo_settings_result_title]);
        config(['dbtable.seo_settings_task' => $seo_settings_task]);
        config(['dbtable.seo_submission_websites' => $seo_submission_websites]);
        config(['dbtable.seo_website_result' => $seo_website_result]);
        config(['dbtable.seo_websites' => $seo_websites]);
        config(['dbtable.seo_work_report' => $seo_work_report]);
        config(['dbtable.seo_website_keyword' => $seo_website_keyword]);
        config(['dbtable.seo_website_ranking' => $seo_website_ranking]);
        //end


        //Landing Table
        config(['dbtable.landing_coupon' => $landing_coupon]);
        config(['dbtable.landing_web_faq' => $landing_web_faq]);
        config(['dbtable.landing_web_faq_group' => $landing_web_faq_group]);
        config(['dbtable.landing_users' => $landing_users]);
        config(['dbtable.landing_web_pages' => $landing_web_pages]);
        config(['dbtable.landing_web_pages_description' => $landing_web_pages_description]);
        config(['dbtable.landing_web_testimonial' => $landing_web_testimonial]);
        config(['dbtable.landing_web_enquiry' => $landing_web_enquiry]);
        config(['dbtable.landing_web_pages_description' => $landing_web_pages_description]);
        config(['dbtable.landing_web_testimonial' => $landing_web_testimonial]);
        config(['dbtable.landing_web_enquiry' => $landing_web_enquiry]);
        config(['dbtable.landing_web_posts' => $landing_web_posts]);
        config(['dbtable.landing_web_post_descriptions' => $landing_web_post_descriptions]);
        config(['dbtable.landing_web_seometa' => $landing_web_seometa]);
        config(['dbtable.landing_sub_plan' => $landing_sub_plan]);
        config(['dbtable.landing_sub_plan_feature' => $landing_sub_plan_feature]);
        config(['dbtable.landing_sub_plan_group' => $landing_sub_plan_group]);
        config(['dbtable.landing_sub_plan_pricing' => $landing_sub_plan_pricing]);
        config(['dbtable.landing_sub_plan_to_feature' => $landing_sub_plan_to_feature]);
        config(['dbtable.landing_app_images' => $landing_app_images]);
        config(['dbtable.landing_web_menu' => $landing_web_menu]);
        config(['dbtable.landing_web_menu_details' => $landing_web_menu_details]);
        config(['dbtable.landing_web_menu_group' => $landing_web_menu_group]);
        config(['dbtable.landing_crm_form' => $landing_crm_form]);
        config(['dbtable.landing_crm_form_data' => $landing_crm_form_data]);
        config(['dbtable.landing_crm_form_fields' => $landing_crm_form_fields]);
        config(['dbtable.landing_web_links' => $landing_web_links]);

        //crm landing
        config(['dbtable.landing_crm_agent' => $landing_crm_agent]);
        config(['dbtable.landing_crm_tags' => $landing_crm_tags]);
        config(['dbtable.landing_crm_lead_to_tags' => $landing_crm_lead_to_tags]);
        config(['dbtable.landing_crm_industry' => $landing_crm_industry]);
        config(['dbtable.landing_crm_lead' => $landing_crm_lead]);
        config(['dbtable.landing_crm_lead_contact' => $landing_crm_lead_contact]);
        config(['dbtable.landing_crm_lead_followup' => $landing_crm_lead_followup]);
        config(['dbtable.landing_crm_lead_followup_history' => $landing_crm_lead_followup_history]);
        config(['dbtable.landing_crm_lead_social_link' => $landing_crm_lead_social_link]);
        config(['dbtable.landing_crm_notes' => $landing_crm_notes]);
        config(['dbtable.landing_crm_lead_source' => $landing_crm_lead_source]);
        config(['dbtable.landing_crm_lead_status' => $landing_crm_lead_status]);

        config(['dbtable.landing_app_reminder_setting' => $landing_app_reminder_setting]);
        config(['dbtable.landing_app_reminder_tracker' => $landing_app_reminder_tracker]);


        //end  crm landing

    }

    /*  store notification here
    {
    param: user_id,type,message,title
    [
    user_id => '',
    type => '',
    msg => '',
    title => ''
    ]
    }
     */

    public static function generate_notification($data = array())
    {

        extract($data);

        $res = NotificationMessage::create([
            "n_type" => $type,
            "n_title" => $title,
            "n_message" => $msg,
            'created_at' => date("Y-m-d h:s:i"),
        ]);

        $response = Notification::create([
            "notification_id" => $res->id,
            "user_id" => $user_id,
            "is_read" => 0,
        ]);

        return $res;
    }

    public static function realtimeNoti($data = array())
    {

        $res = Self::generate_notification($data);
        // dispatch private event
        PrEventInternal::dispatch(json_encode($res));
        return $res;
    }


    // Generate Invoice Number

    public static function generate_invoice_number($type, $size)
    {
        $token = '';

        $alphabet = range("A", "Z");
        $numeric = range("1", "100");
        if ($size > 0) {
            switch ($type) {
                case 'numeric':
                    shuffle($numeric);
                    $res = array_chunk($numeric, $size, true);
                    $token = substr(implode('', $res[0]), 0, $size);
                    break;
                case 'alphabet':
                    shuffle($alphabet);
                    $res = array_chunk($alphabet, $size, true);
                    $token = substr(implode('', $res[0]), 0, $size);
                    break;
                case 'alpha_numeric':
                    $alphabet_num = array_merge($alphabet, $numeric);
                    shuffle($alphabet_num);
                    $res = array_chunk($alphabet_num, $size, true);
                    $token = substr(implode('', $res[0]), 0, $size);
                    break;
                case 'token':
                    $alphabet_num = array_merge($alphabet, $numeric);
                    shuffle($alphabet_num);
                    $res = array_chunk($alphabet_num, $size, true);
                    $token = substr(implode('', $res[0]), 0, $size);
                    break;

                default:

                    break;
            }
        }


        return $token;
    }





    /*
    type: numeric,alphabet,alpha_numeric,token
    */

    public static function generate_random_token($type, $size)
    {





        // $token = '';

        // $alphabet = range("A", "Z");
        // $numeric = range("1", "100");

        // switch ($type) {
        //     case 'numeric':
        //         shuffle($numeric);
        //         $res = array_chunk($numeric, $size, true);
        //         $token = substr(implode('', $res[0]), 0, $size);
        //         break;
        //     case 'alphabet':
        //         shuffle($alphabet);
        //         $res = array_chunk($alphabet, $size, true);
        //         $token = substr(implode('', $res[0]), 0, $size);
        //         break;
        //     case 'alpha_numeric':
        //         $alphabet_num = array_merge($alphabet, $numeric);
        //         shuffle($alphabet_num);
        //         $res = array_chunk($alphabet_num, $size, true);
        //         $token = substr(implode('', $res[0]), 0, $size);
        //         break;
        //     case 'token':
        //         $alphabet_num = array_merge($alphabet, $numeric);
        //         shuffle($alphabet_num);
        //         $res = array_chunk($alphabet_num, $size, true);
        //         $token = substr(implode('', $res[0]), 0, $size);
        //         break;

        //     default:

        //         break;
        // }

        // return $token;





        $token = '';
        $alphabet = range("A", "Z");
        $small_alphabet = range("a", "z");
        $numeric = range("1", "100");

        switch ($type) {
            case 'numeric':
                shuffle($numeric);
                $res = array_chunk($numeric, $size, true);
                $token = substr(implode('', $res[0]), 0, $size);
                break;
            case 'alphabet':
                shuffle($alphabet);
                $res = array_chunk($alphabet, $size, true);
                $token = substr(implode('', $res[0]), 0, $size);
                break;
            case 'smallalphabet':
                shuffle($small_alphabet);
                $res = array_chunk($small_alphabet, $size, true);
                $token = substr(implode('', $res[0]), 0, $size);
                break;
            case 'alpha_numeric':
                $alphabet_num = array_merge($alphabet, $numeric);
                shuffle($alphabet_num);
                $res = array_chunk($alphabet_num, $size, true);
                $token = substr(implode('', $res[0]), 0, $size);
                break;
            case 'token':
                $alphabet_num = array_merge($alphabet, $numeric);
                shuffle($alphabet_num);
                $res = array_chunk($alphabet_num, $size, true);
                $token = substr(implode('', $res[0]), 0, $size);
                break;
            case 'password':
                $alphabet_num = array_merge($small_alphabet, $numeric);
                shuffle($alphabet_num);
                $res = array_chunk($alphabet_num, $size, true);
                $token = substr(implode('', $res[0]), 0, $size);
                break;

            default:

                break;
        }

        return $token;
    }

    public static function GenerateAndCheck($model, $column_name, $sizeArray)
    {

        $generatedNumber = Self::generate_random_token('alphabet', $sizeArray[0]) . Self::generate_random_token('alpha_numeric', $sizeArray[1]);
        $modelpath = str_replace('"', "", 'App\Models' . '\\' . $model);
        $availabel = $modelpath::where($column_name, $generatedNumber)->first();
        if (!empty($availabel)) {
            Self::GenerateAndCheck($model, $column_name, $sizeArray);
        } else {
            return $generatedNumber;
        }
    }


    public static function getUniqueValue($model, $column_name, $sizeArray, $slug = "", $status = false)
    {
        $generatedSlug = '';

        if (!empty($slug)) {
            $generatedSlug .= Str::slug($slug);
        }

        $model_location = '';

        if (!empty($model_location)) {
            $model_location = "\\" . $model_location;
        }

        $modelpath = str_replace('"', "", 'Modules\WebsiteSetting\Models' . $model_location . '\\' . $model);

        if ($status == true) {

            $generatedSlug .= '-' . Self::generate_random_token('alphabet', $sizeArray[0]) . Self::generate_random_token('alpha_numeric', $sizeArray[1]);
        }

        $availabel = $modelpath::where($column_name, $generatedSlug)->first();

        if (!empty($availabel)) {
            Self::getUniqueValue($model, $column_name, $sizeArray, $slug, true);
        } else {

            return $generatedSlug;
        }
    }


    // public static function  getUniqueSlug($slug, $callCount)
    // {
    //     if ($callCount == 0)
    //         $generatedSlug = $slug;
    //     else
    //         $generatedSlug = $slug . '-' . rand(10, 100) . '-' . chr(rand(97, 122));


    //     $availabel = WebPages::where('pages_slug', $generatedSlug)->first();
    //     if (!empty($availabel)) {
    //         $callCount++;
    //         Self::getUniqueSlug($slug, $callCount);
    //     } else {
    //         return $generatedSlug;
    //     }
    // }

    /*

    Get Setting info
    param: DEFAULT_APP_LANGUAGE:OPTONAL
     */
    public static function getSettingInfo($token, $settingKey = '')
    {

        // $res = Setting::select('setting_key','setting_value')->where('user_id',Self::get_adminid_from_token($token));
        $res = Setting::select('setting_key', 'setting_value');

        if (!empty($settingKey)) {
            $res = $res->where('setting_key', $settingKey)->first();
        } else {
            $res = $res->get();
        }

        return $res;
    }
    /*
    get langugae name and translation app_setting wise if admin set default lang to other.
    After Login IF user choose diff language than load transalation list to other lang
     */


    public static function getSettingGrpKeyInfo($keyname)
    {


        $res = AppSettingGroupKey::where('setting_key', $keyname)->first();

        //  if (!empty($settingKey)) {
        //      $res = $res->where('setting_key', $settingKey)->first();
        //  } else {
        //      $res = $res->get();
        //  }

        return $res;
    }

    public static function getLanguageAndTranslation($token)
    {
        // if (!array_key_exists('APP_DEFAULT_LANGUAGE', $token,)) {
        //     $setting = Self::getSettingInfo($token, 'APP_DEFAULT_LANGUAGE');
        // } else {

        //     $setting = Self::getSettingInfo($token, 'APP_DEFAULT_LANGUAGE = 1');
        // }


        $setting = Self::getSettingInfo($token, 'APP_DEFAULT_LANGUAGE');
        if (!empty($setting->setting_value)) {

            $languages_code = $setting->setting_value;
            $lang_key = "backend";

            $language_data = Language::where('languages_code', $languages_code)->first();

            if (!empty($language_data->languages_id)) {
                $data = Translation::where('languages_id', $language_data->languages_id)
                    ->where('lang_key', $lang_key)->first();
            } else {
                $data = Translation::where('languages_id', '1')
                    ->where('lang_key', $lang_key)->first();
            }

            if (!empty($data)) {
                $data->lang = $data->language->lang_value;
            }
            return $data;
        } else {
            return '';
        }
    }



    public static function getFullImageUrl($image_ids, $pageType = '')
    {

        //  $subs_details = self::get_subscription_details_by_api_token($api_token);
        //  $subs_details = self::$subscription;


        $image = '';
        $baseDir = self::$subscription->directory_path ?? '0001';

        $imageInfo = TempImages::find($image_ids);
        if (!empty($imageInfo)) {

            $imageName = $imageInfo->images_name;


            if ($pageType == 'index-list') {
                $imageName .= '_50x50';
            }

            $imageName .= '.' . $imageInfo->images_ext;
            $fullImagePath = $imageInfo->images_directory . '/' . $imageName;

            if (Storage::exists($fullImagePath)) {
                $image = $fullImagePath;
            } else {
                $image = $baseDir . '/' . $imageInfo->images_directory . '/' . $imageInfo->images_name . '.' . $imageInfo->images_ext;
            }
        }

        return (!empty($image)) ? Storage::url($image) : url('no-image.png');
    }

    public static function getLandingImageUrl($image_ids, $pageType = '')
    {

        //  $subs_details = self::get_subscription_details_by_api_token($api_token);
        //  $subs_details = self::$subscription;


        $image = '';
        $baseDir = self::$subscription->directory_path ?? '0001';

        $imageInfo = LandingImages::find($image_ids);
        if (!empty($imageInfo)) {

            $imageName = $imageInfo->images_name;


            if ($pageType == 'index-list') {
                $imageName .= '_50x50';
            }

            $imageName .= '.' . $imageInfo->images_ext;
            $fullImagePath = $imageInfo->images_directory . '/' . $imageName;

            if (Storage::exists($fullImagePath)) {
                $image = $fullImagePath;
            } else {
                $image = $baseDir . '/' . $imageInfo->images_directory . '/' . $imageInfo->images_name . '.' . $imageInfo->images_ext;
            }
        }

        return (!empty($image)) ? Storage::url($image) : url('no-image.png');
    }

    public static function getSuperFullImageUrl($image_ids, $pageType = '')
    {

        $image = '';

        $baseDir = '0001';

        $imageInfo = SuperTempImages::find($image_ids);
        if (!empty($imageInfo)) {

            $imageName = $imageInfo->images_name;

            if ($pageType == 'index-list') {
                $imageName .= '_50x50';
            }

            $imageName .= '.' . $imageInfo->images_ext;
            $fullImagePath = $imageInfo->images_directory . '/' . $imageName;

            if (Storage::exists($fullImagePath)) {
                $image = $fullImagePath;
            } else {
                $image = $baseDir . '/' . $imageInfo->images_directory . '/' . $imageInfo->images_name . '.' . $imageInfo->images_ext;
            }
        }

        return (!empty($image)) ? Storage::url($image) : url('no-image.png');
    }



    public  static function getUserprofileImage($api_token)
    {

        $userId = Self::get_user_id_from_token($api_token);
        $userdetail = User::find($userId);
        $full_image_path = Self::getFullImageUrl($userdetail->profile_photo, 'index-list');

        return $full_image_path;
    }









    public static function getLangid($lang_code)
    {
        $res = Language::where('languages_code', $lang_code)->first();
        return ($res != null) ? $res->languages_id : 0;
    }

    public static function get_subscription_details_by_api_token($token)
    {

        $userEmail = Self::get_parentemail_from_token($token);

        $business = UserBusiness::where('users_email', $userEmail)->first();
        if ($business !== null) {
            return !empty($business->subscription) ? $business->subscription : [];
        } else {
            return [];
        }
    }




    public static function get_subscription_user_id_by_api_token($token)
    {

        $userEmail = Self::get_parentemail_from_token($token);

        $business = UserBusiness::where('users_email', $userEmail)->first();
        if ($business !== null) {
            return $business->users_id;
        } else {
            return [];
        }
    }

    public static function get_subscription_unique_id_by_api_token($token)
    {

        $subscription_details = Self::get_subscription_details_by_api_token($token);
        if (!empty($subscription_details)) {
            return $subscription_details->subscription_unique_id;
        } else {
            return 0;
        }
    }


    public static function get_subscription_id_by_api_token($token)
    {

        $subscription_details = Self::get_subscription_details_by_api_token($token);
        if (!empty($subscription_details)) {
            return $subscription_details->subscription_id;
        } else {
            return 0;
        }
    }






    public static function get_industry_id_by_api_token($token)
    {

        $userEmail = Self::get_parentemail_from_token($token);
        $business = UserBusiness::where('users_email', $userEmail)->first();
        if ($business !== null) {
            return !empty($business->subscription) ? $business->subscription->industry_id : 0;
        } else {
            return 0;
        }
    }

    public static function check_module_status_by_industryid($industry_id, $module_id)
    {

        if ($module_id != 0 && $industry_id != 0) {
            $industry_status = IndustryModule::Where('industry_id', $industry_id)->where('module_id', $module_id)->first();
            if (!empty($industry_status)) {
                return $industry_status->status;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }



    public static function get_db_id_by_api_token($token)
    {

        $userid = Self::get_parentid_from_token($token);
        $business = UserBusiness::where('users_id', $userid)->first();
        if ($business !== null) {
            return !empty($business->subscription) ? $business->subscription->db_suffix : 0;
        } else {
            return 0;
        }
    }


    public static function get_website_url_by_api_token($token)
    {

        $userid = Self::get_parentid_from_token($token);
        $business = UserBusiness::where('users_id', $userid)->first();
        if ($business !== null) {
            return !empty($business->subscription) ? $business->subscription->domain_url : 0;
        } else {
            return 0;
        }
    }


    public static function get_db_id_by_email($email)
    {

        if ($email != '') {
            $business = UserBusiness::where('users_email', $email)->first();
            if ($business !== null) {
                return !empty($business->subscription) ? $business->subscription->db_suffix : 0;
            } else {
                return 0;
            }
        }
    }


    public static function db_folder($api_token)
    {

        $subs_details = self::get_subscription_details_by_api_token($api_token);

        if (!empty($subs_details)) {
            if (isset($subs_details->directory_path)) {
                $mainPath = $subs_details->directory_path;
            } else if ($subs_details->subscription_unique_id) {
                $mainPath = $subs_details->subscription_unique_id;
            } else {
                $mainPath = $subs_details->subscription_unique_id;
            }
        } else {
            $mainPath = '0001';
        }

        $folderPath = '';

        if (!Storage::exists($mainPath)) {
            Storage::makeDirectory($mainPath, 0777, true, true);
        }


        return  $mainPath;

        // if ($folderPath != '') {
        //     if (!Storage::exists($mainPath . $folderPath)) // check exist
        //     {
        //         Storage::makeDirectory($mainPath . $folderPath, 0777, true, true);
        //     }

        // }

        //     return  $folderPath;


    }


    public static function getImageUrl($api_token, $image_ids, $pageType = '')
    {

        $subs_details = self::get_subscription_details_by_api_token($api_token);
        //  $subs_details = self::$subscription;

        if (!empty($subs_details)) {
            if (isset($subs_details->directory_path)) {
                $baseDir = $subs_details->directory_path;
            } else if ($subs_details->subscription_unique_id) {
                $baseDir = $subs_details->subscription_unique_id;
            } else {
                $baseDir = $subs_details->subscription_unique_id;
            }
        } else {
            $baseDir = '0001';
        }


        $image = '';
        //   $baseDir = self::$subscription->directory_path ?? '0001';

        $imageInfo = TempImages::find($image_ids);
        if (!empty($imageInfo)) {

            $imageName = $imageInfo->images_name;


            if ($pageType == 'index-list') {
                $imageName .= '_50x50';
            }

            $imageName .= '.' . $imageInfo->images_ext;
            $fullImagePath = $imageInfo->images_directory . '/' . $imageName;

            if (Storage::exists($fullImagePath)) {
                $image = $fullImagePath;
            } else {
                $image = $baseDir . '/' . $imageInfo->images_directory . '/' . $imageInfo->images_name . '.' . $imageInfo->images_ext;
            }
        }

        return (!empty($image)) ? Storage::url($image) : url('no-image.png');
    }



    /***
    api_token : to fetch subscrberId
    imageid: temp image upload id
    image_type: product, gallery, category, setting etc
    file_name : create sub_new folder like product 1 will store all crop image inside some 1 folder
    sub_image_type : image crop size like in 4, 3,2 size etc
     ***/

    public static function image_upload_with_crop($api_token, $images_id, $image_type, $file_name, $sub_image_type = '', $multisize = true, $img_size_ary = [])
    {
        // $images_ori_name
        try {

            $product_size = ["500x500", "250x250", "50x50"];
            $gallery_size = ["500x500", "50x50"];
            $media_size = ["500x500"];
            // $banner_size = ["1000x1000"];

            if ($multisize) {
                if ($image_type == 2) {
                    if ($sub_image_type == 'gallery') {
                        $size_array = (!empty($img_size_ary)) ? $img_size_ary : $gallery_size;
                    } else {
                        $size_array = (!empty($img_size_ary)) ? $img_size_ary : $product_size;
                    }
                } else if ($image_type == 1) {
                    $size_array = (!empty($img_size_ary)) ? $img_size_ary : $media_size;
                } else {
                    $size_array = (!empty($img_size_ary)) ? $img_size_ary : $media_size;
                }
            } else {
                $size_array = [];
            }

            //$SubsID= ApiHelper::get_subscription_id_by_api_token($api_token);
            //$subs_details = ApiHelper::get_subscription_details_by_api_token($api_token);
            //self::init($api_token);
            $subs_details = self::$subscription;

            if (!empty($subs_details)) {
                if (isset($subs_details->directory_path)) {
                    $mainPath = $subs_details->directory_path;
                } else if ($subs_details->subscription_unique_id) {
                    $mainPath = $subs_details->subscription_unique_id;
                } else {
                    $mainPath = $subs_details->subscription_unique_id;
                }
            } else {
                $mainPath = '0001';
            }

            $folderPath = '';

            if (!Storage::exists($mainPath)) {
                Storage::makeDirectory($mainPath, 0777, true, true);
            }




            $imageInfo = TempImages::find($images_id);




            // return $imageInfo;

            if (!empty($imageInfo)) {
                switch ($image_type) {
                    case (1):

                        $folderPath = '/media/' . $file_name;

                        break;

                    case (2):

                        $folderPath = '/product/' . $file_name;

                        if (!empty($sub_image_type)) {
                            $folderPath = $folderPath . '/' . $sub_image_type;
                        }

                        break;
                    case (3):

                        $folderPath = '/category/' . $file_name;

                        if (!empty($sub_image_type)) {
                            $folderPath = $folderPath . '/' . $sub_image_type;
                        }

                        break;

                    case (4):

                        $folderPath = '/attachment/' . $file_name;

                        if (!empty($sub_image_type)) {
                            $folderPath = $folderPath . '/' . $sub_image_type;
                        }



                        break;
                    default:
                        // code...
                        break;
                }
            }

            if ($folderPath != '') {
                if (!Storage::exists($mainPath . $folderPath)) // check exist
                {
                    Storage::makeDirectory($mainPath . $folderPath, 0777, true, true);
                }

                // create image
                $imageName = $imageInfo->images_name . '.' . $imageInfo->images_ext;
                // $tempImage = Storage::path($imageInfo->images_directory.'/'.$imageName);
                $tempImage = $imageInfo->images_directory . '/' . $imageName;
                $liveImage = $mainPath . $folderPath . '/' . $imageName;
                // return $tempImage;

                // attach directory to db
                $imageInfo->images_directory = $folderPath;
                $imageInfo->images_type = $image_type;
                if (!empty($size_array)) {
                    $imageInfo->images_size = json_encode($size_array);
                } else {
                    $imageInfo->images_size = json_encode($size_array);
                }

                $imageInfo->images_status = 'live';

                if (Storage::exists($tempImage)) {
                    if (!Storage::exists($liveImage)) {
                        Storage::move($tempImage, $liveImage);
                    }
                }

                // ftp://support@143.198.237.54/storage/app/public/temp/1647933995/1647933995.jpg

                if (!empty($size_array)) {
                    foreach ($size_array as $key => $size) {
                        $exp = explode("x", $size);
                        $width = $exp[0];
                        $height = $exp[1];

                        $image_resize = Image::make(Storage::path($liveImage));
                        $image_resize->resize((int) $width, (int) $height);

                        $saveName = Storage::path($mainPath . $folderPath . '/' . $imageInfo->images_name . '_' . $size . '.' . $imageInfo->images_ext);
                        $image_resize->save($saveName);
                    }
                }

                $imageInfo->save();
            }

            return $imageInfo;
        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
            return $th->getMessage();
        }
    }



    public static function landing_image_upload_with_crop($api_token, $images_id, $image_type, $file_name, $sub_image_type = '', $multisize = true, $img_size_ary = [])
    {
        // $images_ori_name
        try {

            $product_size = ["500x500", "250x250", "50x50"];
            $gallery_size = ["500x500", "50x50"];
            $media_size = ["500x500"];
            // $banner_size = ["1000x1000"];

            if ($multisize) {
                if ($image_type == 2) {
                    if ($sub_image_type == 'gallery') {
                        $size_array = (!empty($img_size_ary)) ? $img_size_ary : $gallery_size;
                    } else {
                        $size_array = (!empty($img_size_ary)) ? $img_size_ary : $product_size;
                    }
                } else if ($image_type == 1) {
                    $size_array = (!empty($img_size_ary)) ? $img_size_ary : $media_size;
                } else {
                    $size_array = (!empty($img_size_ary)) ? $img_size_ary : $media_size;
                }
            } else {
                $size_array = [];
            }

            //$SubsID= ApiHelper::get_subscription_id_by_api_token($api_token);
            //$subs_details = ApiHelper::get_subscription_details_by_api_token($api_token);
            //self::init($api_token);
            $subs_details = self::$subscription;

            if (!empty($subs_details)) {
                if (isset($subs_details->directory_path)) {
                    $mainPath = $subs_details->directory_path;
                } else if ($subs_details->subscription_unique_id) {
                    $mainPath = $subs_details->subscription_unique_id;
                } else {
                    $mainPath = $subs_details->subscription_unique_id;
                }
            } else {
                $mainPath = '0001';
            }

            $folderPath = '';

            if (!Storage::exists($mainPath)) {
                Storage::makeDirectory($mainPath, 0777, true, true);
            }



            $imageInfo = LandingImages::find($images_id);




            // return $imageInfo;

            if (!empty($imageInfo)) {
                switch ($image_type) {
                    case (1):

                        $folderPath = '/media/' . $file_name;

                        break;



                    case (2):

                        $folderPath = '/attachment/' . $file_name;

                        if (!empty($sub_image_type)) {
                            $folderPath = $folderPath . '/' . $sub_image_type;
                        }



                        break;
                    default:
                        // code...
                        break;
                }
            }

            if ($folderPath != '') {
                if (!Storage::exists($mainPath . $folderPath)) // check exist
                {
                    Storage::makeDirectory($mainPath . $folderPath, 0777, true, true);
                }

                // create image
                $imageName = $imageInfo->images_name . '.' . $imageInfo->images_ext;
                // $tempImage = Storage::path($imageInfo->images_directory.'/'.$imageName);
                $tempImage = $imageInfo->images_directory . '/' . $imageName;
                $liveImage = $mainPath . $folderPath . '/' . $imageName;
                // return $tempImage;

                // attach directory to db
                $imageInfo->images_directory = $folderPath;
                $imageInfo->images_type = $image_type;
                if (!empty($size_array)) {
                    $imageInfo->images_size = json_encode($size_array);
                } else {
                    $imageInfo->images_size = json_encode($size_array);
                }

                $imageInfo->images_status = 'live';

                if (Storage::exists($tempImage)) {
                    if (!Storage::exists($liveImage)) {
                        Storage::move($tempImage, $liveImage);
                    }
                }

                // ftp://support@143.198.237.54/storage/app/public/temp/1647933995/1647933995.jpg

                if (!empty($size_array)) {
                    foreach ($size_array as $key => $size) {
                        $exp = explode("x", $size);
                        $width = $exp[0];
                        $height = $exp[1];

                        $image_resize = Image::make(Storage::path($liveImage));
                        $image_resize->resize((int) $width, (int) $height);

                        $saveName = Storage::path($mainPath . $folderPath . '/' . $imageInfo->images_name . '_' . $size . '.' . $imageInfo->images_ext);
                        $image_resize->save($saveName);
                    }
                }

                $imageInfo->save();
            }

            return $imageInfo;
        } catch (\Throwable $th) {
            \Log::error($th->getMessage());
            return $th->getMessage();
        }
    }



    public static function read_csv_data($fileInfo, $path)
    {

        $file = $fileInfo;
        $path = $file->store($path);
        $file_path = Storage::path($path);
        $file = fopen($file_path, "r");
        $dataList = fgetcsv($file);
        $all_data = [];
        while (($data = fgetcsv($file)) !== false) {
            array_push($all_data, $data);
        }
        return $all_data;
    }

    public static function encrypt_string($string)
    {

        $count = 2;
        $cryptString = $string;

        for ($i = 0; $i < $count; $i++) {
            $cryptString = base64_encode($cryptString);
        }

        return $cryptString;
    }

    public static function decrypt_string($string)
    {

        $count = 2;
        $cryptString = $string;

        for ($i = 0; $i < $count; $i++) {
            $cryptString = base64_decode($cryptString);
        }

        return $cryptString;
    }

    public static function shareEncryptToken($token)
    {
        $sharetoken = '';
        $db_id = '';

        $business_db_id = Self::get_db_id_by_api_token($token);
        $db_id = "db::" . !empty($business_db_id) ? $business_db_id : '';

        $sharetoken = Self::encrypt_string($db_id);
        return $sharetoken;
    }

    public static function base64url_encode($data)
    {
        return rtrim(strtr($data, '+/', '-_'), '=');
    }

    public static function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    // get direct setting value
    public static function getKeySetVal($keyname)
    {
        $set_val = Setting::where('setting_key', $keyname)->first();
        return (!empty($set_val)) ? $set_val->setting_value : '';
    }

    // get direct setting value
    public static function KeySetVal($keyname)
    {
        $set_val = SettingKeyValue::where('setting_key', $keyname)->first();
        return (!empty($set_val)) ? $set_val->setting_value : '';
    }



    // get direct web setting value
    public static function getKeyVal($keyname)
    {
        $set_val = WebsiteSettingKeyValue::where('setting_key', $keyname)->first();
        return (!empty($set_val)) ? $set_val->setting_value : '';
    }

    public static function convertToSingleQuote($valWithComma)
    {
        $list = [];

        $res = explode(',', $valWithComma);
        foreach ($res as $key => $r) {
            $stringCoon = "'" . $r . "'";
            array_push($list, $stringCoon);
        }
        return implode(",", $list);
    }



    /* default currency */
    public static function defaulyCurrncy()
    {
        return Self::getKeyVal('web_default_currency');
    }

    /* default default_language */
    public static function defaultLanguage()
    {
        return Self::getKeyVal('web_default_language');
    }


    public static function defaultLanguageSup()
    {
        return Self::getKeySetVal('default_language');
    }


    public static function defaultCountry()
    {
        return Self::getKeySetVal('default_country');
    }


    public static function invoice_biller_address()
    {
        return Self::KeySetVal('invoice_biller_address');
    }



    public static function otherOperationalCountry()
    {
        return Self::KeySetVal('other_operational_country');
    }


    //tax_type
    public static function taxType()
    {
        return Self::getKeySetVal('tax_type');
    }


    //enable tax
    public static function enableTax()
    {
        return Self::getKeySetVal('ENABLE_TAX');
    }




    /* default time_zone */
    public static function timeZone()
    {
        return Self::getKeyVal('time_zone');
    }

    // /* default date_format */
    // public static function dateFormat(){
    //     return Self::getKeyVal('date_format');
    // }

    /* default date_format */
    public static function dateFormat()
    {
        $whereQ = Self::convertToSingleQuote(Self::getKeyVal('date_format'));
        return DateFormat::whereRaw('date_format IN (' . $whereQ . ')')->get();
    }


    public static function Currancy()
    {

        $whereQ = Self::convertToSingleQuote(Self::getKeyVal('web_default_currency'));
        return Currency::whereRaw('currencies_code IN (' . $whereQ . ')')->get();
    }



    /* per page item */
    public static function perPageItem()
    {
        return Self::getKeySetVal('per_page_item');
    }



    public static function landingDomainUrl()
    {
        return Self::getKeySetVal('landing_domain');
    }

    /* default date_format */
    public static function otherSupportLang()
    {
        $whereQ = Self::convertToSingleQuote(Self::getKeyVal('web_other_supported_language'));
        return Language::whereRaw('languages_code IN (' . $whereQ . ')')->get();
    }

    /* other support Currency */
    public static function otherSupportCurrency()
    {
        $whereQ = Self::convertToSingleQuote(Self::getKeyVal('web_other_supported_currency'));
        return Currency::whereRaw('currencies_code IN (' . $whereQ . ')')->get();
    }

    public static function db_user_id()
    {
        return "0001-1";
    }

    public static function allSupportLang()
    {
        $dLang = self::defaultLanguage();
        $oLang = Self::getKeyVal('web_other_supported_language');
        $whereQ = Self::convertToSingleQuote($oLang . ',' . $dLang);
        return Language::whereRaw('languages_code IN (' . $whereQ . ')')->get();
    }

    public static function allSupportCountry()
    {
        $dCntry = self::defaultCountry();

        $oCntry = Self::getKeySetVal('other_operational_country');

        $whereQ = Self::convertToSingleQuote($dCntry . ',' . $oCntry);

        return Country::whereRaw('countries_iso_code_2 IN (' . $whereQ . ')')->get();
    }


    public static function allSuperSupportLang()
    {
        $dLang = self::defaultLanguageSup();
        $oLang = Self::getKeySetVal('other_supported_language');
        $whereQ = Self::convertToSingleQuote($oLang . ',' . $dLang);
        return Language::whereRaw('languages_code IN (' . $whereQ . ')')->get();
    }

    public static function getGroupName($id)
    {
        $TaxGroup = CRMSettingTaxGroup::where('tax_group_id', $id)->first();
        return $TaxGroup->tax_group_name ?? '';
    }


    // mail_template

    public static function getTemplate($key)
    {
        $temp_arr = [];
        $emailGroup = SuperEmailGroup::with('template')->where('group_key', $key)->first();
        if (!empty($emailGroup)) {
            if (isset($emailGroup->template)) {
                $temp = $emailGroup->template()->where('status', 1)->inRandomOrder()->first();
                if (!empty($temp)) {
                    $temp_arr['template_subject'] = $temp->template_subject;
                    $temp_arr['template_content'] = $temp->template_content;
                    $temp_arr['group_id'] = $emailGroup->group_id;
                }
            }
        }
        return $temp_arr;
    }



    public static function getNameAndDesc($type, $data)
    {
        $language = Self::getLangid(1);

        switch ($type) {

            case 'product':

                // if(is_array($data)){
                $product = $data->map(function ($data) use ($language) {
                    $cate = $data->productdescription()->where('languages_id', $language)->first();
                    $data->products_name = ($cate == null) ? '' : $cate->products_name;
                    $data->products_description = ($cate == null) ? '' : $cate->products_description;
                    return $data;
                });
                return $product;

                // }else{

                // $pr = $data->description()->where('languages_id', $language)->first();
                // $data->products_name = ($pr == null) ? '' : $pr->products_name;
                // $data->products_description = ($pr == null) ? '' : $pr->products_description;
                // return $data;

                // }

                break;

            case 'category':

                // if(is_array($data)){
                $category = $data->map(function ($CAT) use ($language) {
                    $cate = $CAT->description()->where('languages_id', $language)->first();
                    $CAT->category_name = ($cate == null) ? '' : $cate->categories_name;
                    $CAT->category_description = ($cate == null) ? '' : $cate->categories_description;
                    return $CAT;
                });
                return $category;

                // }else{

                //     $cat = $data->description()->where('languages_id', $language)->first();
                //     $data->category_name = ($cat == null) ? '' : $cat->categories_name;
                //     $data->category_description = ($cat == null) ? '' : $cat->categories_description;
                //     return $data;

                // }

                break;

            default:
                # code...
                break;
        }
    }




    public static function addOnsetting()
    {

        $sett = [];

        // Add on Settings

        $addOnSetting = AddOnSetting::all();
        if (!empty($addOnSetting)) {
            foreach ($addOnSetting as $addSetting) {
                $sett[$addSetting->setting_key] = $addSetting->setting_value;
            }
        }
        return $sett;
    }

    public static function getAddOnSetVal($keyname)
    {
        $set_val = AddOnSetting::where('setting_key', $keyname)->first();
        return (!empty($set_val)) ? $set_val->setting_value : '';
    }


    public static function getCurrencyName($currCode)
    {
        $curr_val = Currency::where('currencies_code', $currCode)->first();
        return (!empty($curr_val)) ? $curr_val->currencies_name : '';
    }


    public static function userType($api_token)
    {

        $p_email = Self::get_parentemail_from_token($api_token);
        $userBus = UserBusiness::where('users_email', $p_email)->first();
        if (!empty($userBus)) {
            $userType = "subscriber";
        } else {
            $userType = '';
        }

        return  $userType;
    }
}
