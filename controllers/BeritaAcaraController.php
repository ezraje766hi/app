<?php

namespace esk\controllers;

use Yii;
use esk\models\EskBeritaAcaraOther;
use esk\models\EskBeritaAcaraDetailOther;
use esk\models\EskBeritaAcaraDetailOtherTemp;
use esk\models\BeritaAcara;
use esk\models\BeritaAcaraSearch;
use esk\models\EvaluationData;
use esk\models\Employee;
use esk\models\Position;
use esk\models\EskJarak;
use esk\models\EskLists;
use esk\models\EskListsTemp;
use esk\models\GenerateEsk;
use esk\models\EskApprovalMaster;
use esk\models\EskApprovalDetail;
use esk\models\EskApprovalLists;
use esk\models\EskAcknowledgeLists;
use esk\models\BeritaAcaraDetailSearch;
use esk\models\EskTemplateMaster;
use esk\models\EskTunjabNss;
use esk\models\EskCodeParam;
use esk\models\EskListHcbp;
use esk\models\EskSo;
use moonland\phpexcel\Excel;
use esk\models\Model;
use common\models\City;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use esk\components\Helper;
use yii\web\UploadedFile;

// add by faqih sprint 3
use esk\models\EskGroupReasonData;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
// end

/**
 * BeritaAcaraController implements the CRUD actions for BeritaAcara model.
 */
class BeritaAcaraController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['login', 'error','logout'],
                        'allow' => true,
                    ],
                    [
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            //validation menu approved lists
                            if(Model::countApprovalManagerBA() <= 0){
                                $checkRoleApprovalManagerBA = false;
                            }else{
                                $checkRoleApprovalManagerBA = true;
                            }

                            if($checkRoleApprovalManagerBA){
                                return $checkRoleApprovalManagerBA;
                            }

                            if(Model::countVeirificationBA() <= 0){
                                $checkRoleVerificationBA = false;
                            }else{
                                $checkRoleVerificationBA = true;
                            }

                            if($checkRoleVerificationBA){
                                return $checkRoleVerificationBA;
                            }

                            //check if role sysadmin
                            if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing') || Yii::$app->user->can('hcbp_account')){
                               return true; 
                            }else{
                                return false;
                            }
                        }
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all BeritaAcara models.
     * @return mixed
     */
    public function actionIndex()
    {   
        $searchModel = new BeritaAcaraSearch();
        $searchModel2 = new EskBeritaAcaraOther();

        $area = Yii::$app->user->identity->employee->area;
        if($area != 'HEAD OFFICE')
            $searchModel->area = Yii::$app->user->identity->employee->area;
            $searchModel2->area = Yii::$app->user->identity->employee->area;
        
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider2 = $searchModel2->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'searchModel2' => $searchModel2,
            'dataProvider' => $dataProvider,
            'dataProvider2' => $dataProvider2,
        ]);
    }

    public function actionApprovalBa()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        
        $searchModel = new EskBeritaAcaraDetailOther();
        if(Yii::$app->user->can('sysadmin')){
            $query = "esk_berita_acara_other.status = 1 AND esk_berita_acara_detail_other.flag_esk = 0 AND esk_berita_acara_detail_other.flag_ba_manager = 0";
        }else{
            $query = "esk_berita_acara_other.status = 1 && esk_berita_acara_other.approved_by = '".Yii::$app->user->identity->nik."' AND esk_berita_acara_detail_other.flag_esk = 0";
        }
        
        $dataProvider = $searchModel->beritaAcaraDetailApproval(Yii::$app->request->queryParams,$query);
        $dataProvider->setPagination(['pageSize' => 100]);
        //$dataProvider->pagination->pageSize = 1;
        
        return $this->render('approval_ba', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel
        ]);
    }
    
    public function actionApprovalManagerBa()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        
        $searchModel = new EskBeritaAcaraDetailOther();
        /*
        if(Yii::$app->user->can('sysadmin')){
            $query = "esk_berita_acara_other.status = 1 AND esk_berita_acara_detail_other.flag_esk = 0 AND esk_berita_acara_detail_other.flag_ba_manager = 1";
        }else{
             $query = "esk_berita_acara_other.status = 1 && esk_berita_acara_detail_other.nik_approved_ba = '".Yii::$app->user->identity->nik."' AND esk_berita_acara_detail_other.flag_esk = 0 AND esk_berita_acara_detail_other.flag_ba_manager = 1";
        }
        */
        
        $query = "esk_berita_acara_other.status = 1 && esk_berita_acara_detail_other.nik_approved_ba = '".Yii::$app->user->identity->nik."' AND esk_berita_acara_detail_other.flag_esk = 0 AND esk_berita_acara_detail_other.flag_ba_manager = 1";

        $dataProvider = $searchModel->beritaAcaraDetailApproval(Yii::$app->request->queryParams,$query);
        $dataProvider->setPagination(['pageSize' => 100]);
        //$dataProvider->pagination->pageSize = 1;
        
        return $this->render('approval_manager_ba', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel
        ]);
    }
    
    public function actionModalverification(){
        $id = yii::$app->request->get('id');

        return $this->renderAjax('app_dialog_verification',[
            "id" => $id
        ]);
    }
    
    public function actionVerifikasiBa()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        
        $searchModel = new EskBeritaAcaraDetailOther();
        /*
        if(Yii::$app->user->can('sysadmin')){
            $query = "esk_berita_acara_other.status = 2 AND esk_berita_acara_detail_other.flag_esk = 0";
        } else {
        */
            $query = "esk_berita_acara_other.status = 1 && esk_berita_acara_other.created_by = '".Yii::$app->user->identity->employee->person_id."' AND esk_berita_acara_detail_other.flag_esk = 4";
        //}
        $dataProvider = $searchModel->beritaAcaraDetailApproval(Yii::$app->request->queryParams,$query);
        $dataProvider->pagination = ['pageSize' => 50];
        
        return $this->render('verifikasi_ba', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel
        ]);
    }

    public function actionPdfVerifikasi($idMaster)
    {   
        // $model = EskListsTemp::find()->where([$column => $value])->one();
        $baOther = EskBeritaAcaraDetailOther::find()->where(['id_master' => $idMaster])->get();
        foreach ($baOther as $v) {
            $id     = $v['id'];
            $databp = explode(".", $v['new_bp']);
            if(!empty($v['new_bi'])){
                $bi = $v['new_bi'];
            }else{
                $bi = $v['old_bi'];
            }
            $model   = EskListsTemp::find()->where(['id_ba_detail' => $id])->one();
            
            $dataMED = Model::getMutasiEvaDpe(
                $v['old_kota'],
                $v['new_kota'],
                $v['old_kode_kota'],
                $v['new_kode_kota'],
                $v['tipe'],
                $v['dpe']
            );
            //explode data BP 
            $databp = explode(".",$v['new_bp']);
            $dataoldbp = explode(".",$v['old_bp']);
            $data_esk_master = Model::checkTemplateMaster(
                $v['tipe'],
                $v['code_template'],
                $databp[0],
                $v['old_area'],
                $v['new_area'],
                $v['old_directorate']
            );
            if(!empty($data_esk_master)){
                //default value
                $flag_gaji = 1;
                $flag_uang_pisah = null;
                $flag_ganti_rumah = null;
                $flag_ganti_cuti = null;
                $flag_homebase = null;
                $flag_insentif = 1;
                $flag_ket_kerja = 1;

                //get data terkait salary seperti gaji dasar dan tunjangan lainnya
                $salary = Model::getSalaryData($bi,$v['new_bp']);
            
                //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
                if($v['positionNew']->structural == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                    $flag_tunjangan_jabatan = 1;
                    $strukctural_data = $v['positionNew']->structural;
                    $functional_data = null;
                }elseif($v['positionNew']->functional == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                    $flag_tunjangan_jabatan = 1;
                    $strukctural_data = null;
                    $functional_data = $v['positionNew']->functional;
                }elseif(strpos($v['new_title'], 'Senior Staff') !== false || ($v['band'] == 1 && $v['level_band'] <= 1) || strpos($v['new_title'], 'Senior Advisor Associate') !== false || strpos($v['new_title'], 'Advisor Associate') !== false || strpos($v['new_title'], 'Telkomsel Next Gen Associate') !== false || strpos($v['new_title'], 'Senior Associate') !== false) { //sini buru
                    $flag_tunjangan_jabatan = 0;
                    $strukctural_data = null;
                    $functional_data = null;
                }else{
                    if($v['employee']->structural == "Y"){
                        $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                        $flag_tunjangan_jabatan = 1;
                        $strukctural_data = $v['employee']->structural;
                        $functional_data = null;
                    }elseif($v['employee']->functional == "Y"){
                        $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                        $flag_tunjangan_jabatan = 1;
                        $strukctural_data = null;
                        $functional_data = $v['employee']->functional;
                    }else{
                        $flag_tunjangan_jabatan = 0;
                        $strukctural_data = null;
                        $functional_data = null;
                    }
                }
                
                $flag_gaji_dasar_nss = 0;
                if($v['gaji_dasar_nss'] >= 0 && !empty($v['gaji_dasar_nss'])) {
                    $flag_gaji_dasar_nss = 1;
                }
                

                //var_dump($flag_tunjangan_jabatan, $v['new_title']);exit;
                $content_sk = Model::generateEsk($data_esk_master['id'],$v['nik'],$v['flag_kisel'],$v['last_payroll'],"",$flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja, null, $flag_tunjangan_jabatan);
                //check apakah content ada code periode atau nodin 
                if( (strpos($content_sk,"{periode}") !== false && empty($periode) && empty($v['periode'])) 
                || (strpos($content_sk,"{nota_dinas}") !== false && empty($nodin) && empty($v['nota_dinas']))
                || (strpos($content_sk,"{nama_penyakit}") !== false && empty($nama_penyakit) && empty($v['nama_penyakit']))
                || (strpos($content_sk,"{nominal_insentif}") !== false && empty($nominal_insentif) && empty($v['nominal_insentif']))
                || (strpos($content_sk,"{manual_content_1}") !== false && empty($manual_content_1))
                || (strpos($content_sk,"{manual_content_2}") !== false && empty($manual_content_2))
                || (strpos($content_sk,"{manual_content_3}") !== false && empty($manual_content_3))
                || (strpos($content_sk,"{keterangan_ba_1}") !== false && empty($keterangan_ba_1) && empty($v['keterangan_ba_1']))
                || (strpos($content_sk,"{keterangan_ba_2}") !== false && empty($keterangan_ba_2) && empty($v['keterangan_ba_2'])) 
                || (strpos($content_sk,"{keterangan_ba_3}") !== false && empty($keterangan_ba_3) && empty($v['keterangan_ba_3'])) 
                || (strpos($content_sk,"{keputusan_direksi_1}") !== false && empty($keputusan_direksi_1) && empty($v['keputusan_direksi_1']))
                || (strpos($content_sk,"{keputusan_direksi_2}") !== false && empty($keputusan_direksi_2) && empty($v['keputusan_direksi_2'])) 
                || (strpos($content_sk,"{keputusan_direksi_3}") !== false && empty($keputusan_direksi_3) && empty($v['keputusan_direksi_3']))
                ){
                    $flag_periode = strpos($content_sk,"{periode}") !== false ? '1' : '0';
                    $flag_nodin = strpos($content_sk,"{nota_dinas}") !== false ? '1' : '0';
                    $flag_nama_penyakit = strpos($content_sk,"{nama_penyakit}") !== false ? '1' : '0';
                    $flag_nominal_insentif = strpos($content_sk,"{nominal_insentif}") !== false ? '1' : '0';
                    $flag_content_1 = strpos($content_sk,"{manual_content_1}") !== false ? '1' : '0';
                    $flag_content_2 = strpos($content_sk,"{manual_content_2}") !== false ? '1' : '0';
                    $flag_content_3 = strpos($content_sk,"{manual_content_3}") !== false ? '1' : '0';
                    $flag_ba_1 = strpos($content_sk,"{keterangan_ba_1}") !== false ? '1' : '0';
                    $flag_ba_2 = strpos($content_sk,"{keterangan_ba_2}") !== false ? '1' : '0';
                    $flag_ba_3 = strpos($content_sk,"{keterangan_ba_3}") !== false ? '1' : '0';
                    $flag_kd_1 = strpos($content_sk,"{keputusan_direksi_1}") !== false ? '1' : '0';
                    $flag_kd_2 = strpos($content_sk,"{keputusan_direksi_2}") !== false ? '1' : '0';
                    $flag_kd_3 = strpos($content_sk,"{keputusan_direksi_3}") !== false ? '1' : '0';
                    
                    $data_return = array(
                        "result" => "failed",
                        "remark" => "data BA ".$v['nik']."/".$v['nama']."/".$v['tipe']." failed because some addtional content (BA/KD/Periode/Nota Dinas/Diseases Name/Insentif Amount) is empty!"
                    );
                }

                //validasi effective date dan flag backdate             
                $today = date("Y-m-d");
    
                $selisih = strtotime($v['effective_date']) - strtotime($today);
                $days = floor($selisih / (60*60*24));
                if($days < 0 ){
                    $flag_backdate = 1;
                }else{
                    $flag_backdate = 0;
                }
                $new_effective_date = $v['effective_date'];
                $new_flag_backdate = $flag_backdate;
                

                //replace data content sknya lepar juga tgl_berlaku_sk
                $replace_sk = Model::replaceBA(
                    $id,
                    $data_esk_master['id'],
                    $new_effective_date,
                    $content_sk,
                    $salary,
                    $data_esk_master['decree_title'],
                    $data_esk_master['authority'],
                    $v['periode'],
                    $v['nodin'],
                    $v['manual_content_1'],
                    $v['manual_content_2'],
                    $v['manual_content_3'],
                    $v['keterangan_ba_1'],
                    $v['keterangan_ba_2'],
                    $v['keterangan_ba_3'],
                    $v['keputusan_direksi_1'],
                    $v['keputusan_direksi_2'],
                    $v['keputusan_direksi_3'],
                    $v['nama_penyakit'],
                    $v['nominal_insentif'],
                    $v
                );

                //get atasan
                if(strpos($v['tipe'],"Position Applied from Exchange") !== false){
                    $data_posisi_new = Position::findOne($v['new_position_id']);
                    if(!empty($data_posisi_new)){
                        //get data employee dengan posisition_id
                        $data_employee_new = Employee::find()->where(['position_id' => $v['new_position_id']])->one();
                        if(!empty($data_employee_new)){
                            $atasan_array = Model::getHead($data_employee_new->nik);
                            $atasan = implode(";",$atasan_array);
                        }else{
                            $atasan_array = Model::getHead($v['nik']);
                            $atasan = implode(";",$atasan_array);
                        }
                    }else{
                        $atasan_array = Model::getHead($v['nik']);
                        $atasan = implode(";",$atasan_array);
                    }
                }else{
                    $atasan_array = Model::getHead($v['nik']);
                    $atasan = implode(";",$atasan_array);
                }

                if(!empty($atasan_array)){
                    foreach($atasan_array as $emp){
                        $data_emp = Employee::find()->where(['nik' => $emp])->one();
                        if((strpos(strtolower($data_emp->job_category), "vice president") !== false && ($dataoldbp[0] != 5 || $dataoldbp[0] != 6)) || strpos(strtolower($data_emp->job_category), "director") !== false){
                            if($dataoldbp[0] == 5 || $dataoldbp[0] == 6){
                                //get data gm hcm HO
                                $vp_nik = Model::getGMhcbp($data_esk_master['authority']);
                            }else{
                                $vp_nik = $emp;
                            }
                            break;
                        }else{
                            $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
                        }
                    }
                }else{
                    $data_emp = Employee::find()->where(['nik' => $emp])->one();
                    $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
                }

                //get data employee 
                $emp_ba = Employee::find()->where(['nik' => $v['nik']])->one();

                //set dan save data esk
                $model->attributes = $v['attributes'];
                $model->id_ba_detail = $v['id'];
                if($v == null){
                    $model->nomor_ba = null;
                    $model->ba_date = null;
                }else{
                    $model->nomor_ba = $v['beritaAcaras']->no;
                    $model->ba_date = $v['beritaAcaras']->ba_date;
                }
                $model->about_esk = $data_esk_master['about'];
                $model->no_esk = $data_esk_master['number_esk']; //dari esk template authority
                $model->content_esk = $replace_sk;
                $model->effective_esk_date = $new_effective_date;
                $model->flag_backdate = $new_flag_backdate;
                $model->head_nik = $atasan;
                $model->vp_nik = $vp_nik;
                $model->status = "generated"; //ubah jika sudah bisa approval jadi 'generate' pertama kalinya
                $model->tracking = "Generated"; //ubah sesuai dengan approvalnya nanti
                $model->created_by = Yii::$app->user->identity->id;
                $model->id_template_master = $data_esk_master['id'];
                $model->batch_name = $v['batch_name'];
                $model->id_batch = $v['id_batch'];
                $model->old_position = $v['old_title']; //(!empty($v['positionOld'])) ? $v['positionOld']->nama : $v['old_title'];
                $model->new_position = $v['new_title']; //(!empty($v['positionNew'])) ? $v['positionNew']->nama : $v['new_title'];
                $model->mutasi = $dataMED['mutasi'];
                $model->eva = $dataMED['eva'];
                $model->dpe_ba = $v['dpe'];
                $model->dpe = $dataMED['dpe'];
                $model->authority = $data_esk_master['authority']; //dari esk template authority
                $model->structural = $strukctural_data;
                $model->functional = $functional_data;
                $model->gaji_dasar = $salary['gaji_dasar'];
                $model->tunjangan_biaya_hidup = $salary['tunjangan_biaya_hidup'];
                $model->tunjangan_jabatan = $salary['tunjangan_jabatan'];
                $model->tunjangan_fungsional = $salary['tunjangan_fungsional'];
                $model->tunjangan_rekomposisi = $salary['tunjangan_rekomposisi'];
                $model->level_tbh = $v['new_bi'];
                $model->level_tr = $v['new_bi'];
                // perubahan approval mpp dan sakit berkepanjangan (all band) - 12092024 -ejes                                
                //if((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) && $model->level_band <= 4){
                if ( strpos(strtolower($model->tipe), "sakit berkepanjangan") !== FALSE || strpos(strtolower($model->tipe), "mpp") !== FALSE  )
                {
                    $model->decree_nama = "Indrawan Ditapradana";
                    $model->decree_nik = "7310004";
                    $model->decree_title = "Director Human Capital Management";
                    $model->represented_title = "Direksi Perseroan";                
                }else{
                    if( (strpos(strtolower($model->tipe), "pejabat sementara") !== false || 
                        strpos(strtolower($model->tipe), "mutasi aps") !== false ) && $model->level_band <= 4){
                        // PJS
                        $model->decree_nama = "Indrawan Ditapradana";
                        $model->decree_nik = "7310004";
                        $model->decree_title = "Director Human Capital Management";
                        $model->represented_title = "Direksi Perseroan";
                    //} elseif((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) && $model->level_band >= 5){
                    } elseif((  strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false ) 
                            && $model->level_band >= 5)
                    {
                        $model->decree_nama = "Nugroho";
                        $model->decree_nik = "7610001";
                        $model->decree_title = "President Director";
                        $model->represented_title = "Direksi Perseroan";
                    } else {
                        $model->decree_nama = $data_esk_master['decree_nama'];
                        $model->decree_nik = $data_esk_master['decree_nik'];
                        $model->decree_title = $data_esk_master['decree_title'];
                        $model->represented_title = $data_esk_master['represented_title'];
                    }
                }

                
                $model->is_represented = $data_esk_master['is_represented'];
                $model->city_esk = $data_esk_master['city_esk'];
                $model->file_name = $data_esk_master['file_name'];
                $model->nota_dinas = $v['nota_dinas'];
                $model->periode = $v['periode'];
                $model->nama_penyakit = $v['nama_penyakit'];
                $model->nominal_insentif = $v['nominal_insentif'];
                $model->manual_content_1 = $v['manual_content_1'];
                $model->manual_content_2 = $v['manual_content_2'];
                $model->manual_content_3 = $v['manual_content_3'];
                $model->keterangan_ba_1 = $v['keterangan_ba_1'];
                $model->keterangan_ba_2 = $v['keterangan_ba_2'];
                $model->keterangan_ba_3 = $v['keterangan_ba_3'];
                $model->keputusan_direksi_1 = $v['keputusan_direksi_1'];
                $model->keputusan_direksi_2 = $v['keputusan_direksi_2'];
                $model->keputusan_direksi_3 = $v['keputusan_direksi_3'];
                $model->alamat = (!empty($emp_ba)) ? $emp_ba->alamat : "";

                //set data creator
                if((!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee))){
                    if(Yii::$app->user->can('sysadmin')){
                        $model->created_authority = "HEAD OFFICE";
                        $model->head_created = Employee::find()
                        ->join('JOIN','user','user.nik = employee.nik')
                        ->join('JOIN','auth_assignment','auth_assignment.user_id = user.id')
                        ->where(['auth_assignment.item_name' => 'hc_staffing', 'employee.job_category' => 'manager'])->one()->nik;
                    }else{
                        $model->created_authority =Yii::$app->user->identity->employee->area;
                        $model->head_created = Yii::$app->user->identity->employee->nik_atasan;
                    }
                }else{
                    $model->created_authority = "HEAD OFFICE";
                    $model->head_created = Employee::find()
                    ->join('JOIN','user','user.nik = employee.nik')
                    ->join('JOIN','auth_assignment','auth_assignment.user_id = user.id')
                    ->where(['auth_assignment.item_name' => 'hc_staffing', 'employee.job_category' => 'manager'])->one()->nik;
                }
                $model->flag_ack_seq = 1;
                
                //set  atasan
                // $model->atasan_created = Yii::$app->user->identity->employee->nik_atasan;
                
                if($model->save()){
                    echo "generate preview BA Temp " . $v['nik'] . " berhasil<br/>";
                }else{
                    $error = implode(",",$model->getErrorSummary(true));
                    echo "generate preview BA Temp " . $v['nik'] . " error: " . $error . "<br/>";
                }
            }else{
                continue;
            }
        }
    }
    
    public function actionListApprovalBa()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        
        
        $searchModel = new EskBeritaAcaraDetailOther();
        if(Yii::$app->user->can('sysadmin')){
            $query = "esk_berita_acara_other.status = 1 AND esk_berita_acara_detail_other.flag_esk = 0";
        }else{
            $query = "esk_berita_acara_other.status = 1 && esk_berita_acara_other.created_by = '".Yii::$app->user->identity->employee->person_id."' AND esk_berita_acara_detail_other.flag_esk = 0";
        }

        $dataProvider = $searchModel->beritaAcaraDetailApproval(Yii::$app->request->queryParams,$query);
        
        return $this->render('approval_ba_list', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel
        ]);
    }
    
    public function actionModalapproved(){
        $id = yii::$app->request->get('id');

        return $this->renderAjax('app_dialog',[
            "id" => $id
        ]);
    }
    
    public function actionModalmanagerapproved(){
        $id = yii::$app->request->get('id');

        return $this->renderAjax('app_manager_dialog',[
            "id" => $id
        ]);
    }
    
    public function actionVerificationall(){
        $id_app_data = yii::$app->request->get('id_approval');
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $id_app = explode(",",$id_app_data);
            $countAll = EskBeritaAcaraDetailOther::find()->where(['in','id',$id_app])->count();
            Model::saveLog(Yii::$app->user->identity->username, "approve BA with ID: " . $id_app_data);
    
            EskBeritaAcaraDetailOther::updateAll(
                [
                    'flag_esk' => '0',
                ], [
                    'in', 'id', $id_app
                ]);

            $transaction->commit();

            Yii::$app->session->setFlash('info', 'Successfully verification Data ' . $countAll . ' data');
        } catch (\Exception $e) {
            $transaction->rollBack();
            Model::saveLog(Yii::$app->user->identity->username, " try to approve BA with : " . $id_app_data . ", but error: " . $e->getMessage());
            Yii::$app->session->setFlash('error', 'Failed verification Data ' . $countAll . ' data');
        }

        return $this->redirect(['berita-acara/verifikasi-ba']);
         
    }
    
    //remark and added by ejes 12092024 for esk tipe mpp dan sakit berkepanjangan
    //public function getHeadApprovalBa($band, $nik){  
    public function getHeadApprovalBa($band, $nik, $tipeesk){  
        
        $employee = Employee::findOne(['nik' => $nik]);
        //added esk tipe 12092024
        if( strpos(strtolower($tipeesk), "sakit berkepanjangan") !== FALSE || strpos(strtolower($tipeesk), "mpp") !== FALSE){
            if($employee->band == 5) {
                $data = 'esk';
            } elseif($band >=5 && strpos($employee->title, 'Director') !== false ) {
                $data = 'esk';    
            }else{
                $data = $employee->nik_atasan;
            }
        // end added esk tipe 12092024
        }else{
            if($band <= 3 && $employee->band >= 4) {
                $data = 'esk';
            } elseif($band == 4 && $employee->band == 5) {
                $data = 'esk';
            } elseif($band >=5 && strpos($employee->title, 'Director') !== false ) {
                $data = 'esk';
            } else {
                $data = $employee->nik_atasan;
            }
        }

        return $data;
    }
    
    public function actionApprovedmanagerall(){
        $id_app_data = yii::$app->request->get('id_approval');
        $id_app = explode(",",$id_app_data);
        
        //get data detail
        $data_ba = EskBeritaAcaraDetailOther::find()->where(['in','id',$id_app])->each();

        //inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();

        foreach($data_ba as $ba){
            try {
                $data_ba_new = $ba;
                //remark and added by ejes 12092024 for esk tipe mpp dan sakit berkepanjangan
                //$findNextApprovedBa = $this->getHeadApprovalBa($data_ba_new->level_band,$data_ba_new->nik_approved_ba);
                $findNextApprovedBa = $this->getHeadApprovalBa($data_ba_new->level_band,$data_ba_new->nik_approved_ba,$data_ba_new->tipe);
                

                if($findNextApprovedBa == 'esk') {
                    
                    $data_ba_new->flag_ba_manager = 0;
                    //get data esk_lists_temp
                    $esk_lists_temp = EskListsTemp::find()->where(['id_ba_detail' => $ba->id])->one();
                    if(!empty($esk_lists_temp)){
                        //push esk_temp ke esk_lists
                        $esk_lists = new EskLists();
                        $esk_lists->attributes = $esk_lists_temp->attributes;
                        $esk_lists->old_position = $esk_lists_temp->old_position;
                        $esk_lists->new_position = $esk_lists_temp->new_position;
                        $esk_lists->old_area = $esk_lists_temp->old_area;
                        $esk_lists->new_area = $esk_lists_temp->new_area;
                        // add by faqih
                        $esk_lists->nik_new_atasan = $data_ba_new->nik_new_atasan;
                        $esk_lists->tunjangan_hot_skill = $data_ba_new->tunjangan_hot_skill;
                        $esk_lists->tunjangan_aktualisasi = $data_ba_new->tunjangan_aktualisasi; // sprint 3
                        $esk_lists->dpe_length = $data_ba_new->dpe_length;
                        $esk_lists->dpe_unit = $data_ba_new->dpe_unit;
                        $esk_lists->grade = $data_ba_new->grade;

                            // sprint 3
                        $esk_lists->new_nik = $data_ba_new->new_nik;
                        $esk_lists->notif_stat_date = $data_ba_new->notif_stat_date;
                        $esk_lists->leaving_reason = $data_ba_new->leaving_reason;
                            // end

                        // end
                        //added by ejes, tipe sk sakit berkepanjangan dan mpp
                        if( strpos(strtolower($data_ba_new->tipe), "sakit berkepanjangan") !== FALSE || strpos(strtolower($data_ba_new->tipe), "mpp") !== FALSE){
                            $esk_lists->atasan_created = '76044';
                        }else{
                            
                            if($esk_lists_temp->level_band == 4) {
                                $esk_lists->atasan_created = '76044';
                            } elseif($esk_lists_temp->level_band >= 5){
                                $isEgroup = EskListHcbp::find()->where(['directorate' => $data_ba_new->old_directorate])->andWhere(['egroup' => $data_ba_new->new_egroup])->one();
                                
                                if(empty($isEgroup)) {
                                    $isDirectorate = EskListHcbp::find()->where(['directorate' => $data_ba_new->old_directorate])->andWhere(['egroup' => ''])->one();
                                    
                                    if(empty($isDirectorate)) {
                                        $dataAtasan = Employee::findOne(['nik' => $data_ba_new->created_by]);
                                        $esk_lists->atasan_created = $dataAtasan->nik_atasan;
                                    } else {
                                        $esk_lists->atasan_created = $isDirectorate->nik_atasan;
                                    }
                                } else {
                                    $esk_lists->atasan_created = $isEgroup->nik_atasan;
                                }
                                
                            } else {
                                
                                $dataDeliver = EskListHcbp::findOne(['nik' => $data_ba_new->created_by, 'directorate' => $data_ba_new->old_directorate]);
                                
                                if(!empty($dataDeliver)) {
                                    $esk_lists->atasan_created = $dataDeliver->nik_atasan;
                                } else {
                                    $dataAtasan = Employee::findOne(['nik' => $data_ba_new->created_by]);
                                    $esk_lists->atasan_created = $dataAtasan->nik_atasan;
                                }
                                
                            }
                        }


                        
                        //get data band position 
                        $databp = explode(".",$esk_lists_temp->new_bp);                

                        //get id esk master
                        $esk_template_master = EskTemplateMaster::findOne($esk_lists_temp->id_template_master);
                        //$id_approval = EskApprovalMaster::find()->where(['band' => $databp[0], 'authority_area' => $esk_lists_temp->authority])->andWhere('directorate like "%'.$esk_lists_temp->old_directorate.'%"')->one();
                        if(is_null($databp[0]) == true || $databp[0] == "" || $databp[0] == 0 || empty($databp[0]) && ($esk_lists_temp->level_band != "" || $esk_lists_temp->level_band !=0)) {
                            $id_approval = EskApprovalMaster::find()->where(['band' => $esk_lists_temp->level_band])->andWhere('authority_area like "%'.$esk_lists_temp->authority.'%"')->andWhere('directorate like "%'.$esk_lists_temp->old_directorate.'%"')->one();
                        } else {
                            $id_approval = EskApprovalMaster::find()->where(['band' => $databp[0]])->andWhere('authority_area like "%'.$esk_lists_temp->authority.'%"')->andWhere('directorate like "%'.$esk_lists_temp->old_directorate.'%"')->one();
                        }

                        
                        if(!empty($id_approval)){
                            $esk_lists->id_approval = $id_approval->id;
                            if($esk_lists->save()){
                                //update berita acara detail flag
                                $ba_detail = EskBeritaAcaraDetailOther::findOne($ba->id);
                                $ba_detail->flag_esk = 1;
                                $ba_detail->status = "Approved by BA Approval";
                                $ba_detail->save();

                                //=== save approval esk start ===// 
                                $data_approval = EskApprovalDetail::find()->where(['id_approval_master' => $id_approval->id])->all();
                                // perubahan approval mpp dan sakit berkepanjangan (all band) - 12092024 -ejes
                                // >> if($esk_lists->level_band == 4 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false || strpos(strtolower($esk_lists->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false && $model->level_band <= 4)) {
                                // >>>> 02092024 if($esk_lists->level_band == 4 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false )) { 
                                /*EJES 021024*/
                                if(
                                    ( $esk_lists->level_band == 4 && 
                                        (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== FALSE || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== FALSE )) 
                                            ||
                                         //all band
                                       ( strpos(strtolower($esk_lists->tipe), "sakit berkepanjangan") !== FALSE || strpos(strtolower($esk_lists->tipe), "mpp") !== FALSE)
                                    ) { 
                                        // PJS
                                        $data_a = new EskApprovalLists();
                                        $data_a->id_esk         = $esk_lists->id;
                                        $data_a->approval_nik   = '7310004';
                                        $data_a->approval_name  = 'Indrawan Ditapradana';
                                        $data_a->approval_mail  = 'indrawan_ditapradana@telkomsel.co.id';
                                        $data_a->approval_title = 'Director Human Capital Management';
                                        $data_a->sequence       = 1;
                                        $data_a->save();
                                
                                // perubahan approval mpp dan sakit berkepanjangan - 12092024 -ejes
                                    // >> } elseif($esk_lists->level_band >= 5 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false || strpos(strtolower($esk_lists->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($esk_lists->tipe), "mpp") !== false && $model->level_band >= 5)) {
                                } elseif($esk_lists->level_band >= 5 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false  )) {
                                        $data_a = new EskApprovalLists();
                                        $data_a->id_esk         = $esk_lists->id;
                                        $data_a->approval_nik   = '7610001';
                                        $data_a->approval_name  = 'Nugroho';
                                        $data_a->approval_mail  = 'nugroho@telkomsel.co.id';
                                        $data_a->approval_title = 'President Director';
                                        $data_a->sequence       = 1;
                                        $data_a->save();
                                } else {
                                    foreach($data_approval as $approval){
                                        $data_a = new EskApprovalLists();
                                        $data_a->id_esk = $esk_lists->id;
                                        $data_a->approval_nik = $approval->nik;
                                        $data_a->approval_name = $approval->employee->nama;
                                        $data_a->approval_mail = $approval->employee->email;
                                        $data_a->approval_title = $approval->employee->title;
                                        $data_a->sequence = $approval->sequence;
                                        $data_a->save();
                                    }
                                }

                                $approval_data = EskApprovalLists::find()->where(['id_esk' => $esk_lists->id])->one();
                                $model2 = EskLists::findOne($esk_lists->id);
                                $model2->flag_approval_seq = 1;
                                $model2->status = "processed";
                                //1209 $model2->tracking = "Awaiting approval of ".$approval_data->approval_title;
                                $model2->tracking = "Awaiting approval of ".$approval_data->approval_title;
                                
                                $model2->save();
                                //var_dump($model2->getErrors(),$model2->id);exit;
                                //=== save ack esk start ===//
                                $this->setAcknowlegeLists($esk_template_master->flag_deliver_to, $esk_lists->nik, $esk_lists->old_position, $esk_lists->new_position, $esk_lists->new_position_id, $esk_lists->authority, $esk_lists->id, $esk_lists->new_directorate);
                                //=== save ack esk end ===//

                                //save workflow esk action "Pembuatan eSK oleh Drafter (nama karyawan login)"
                                Model::setWorkFlow($esk_lists->id,"Persetujuan Berita Acara dan pembuatan eSK oleh ".$ba->beritaAcaras->createdBy->nama." - ".$ba->beritaAcaras->createdBy->title."","-");

                                //logging data
                                Model::saveLog(Yii::$app->user->identity->username, "Generate eSK with ID ".$esk_lists->id);

                                $countSuccess = $countSuccess + 1;
                            }else{
                                $countFailed = $countFailed + 1;
                                $error = implode(",",$esk_lists->getErrorSummary(true));
                                array_push($failed_array,"data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because ".$error);
                            }
                        }else{
                            $countFailed = $countFailed + 1;
                            array_push($failed_array,"data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because approval data that matches type of esk not found.");
                        }
                    }else{
                        $countFailed = $countFailed + 1;
                        array_push($failed_array,"data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because eSK data not found, please reject and recreate BA!");
                    }
                } else {
                    $data_ba_new->flag_ba_manager = 1;
                    $data_ba_new->nik_approved_ba = $findNextApprovedBa;
                }

                if(!$data_ba_new->save()) { 
                    $error = implode(",", $data_ba_new->getErrorSummary(true));
                    array_push($failed_array, "data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because " . $error);
                    echo "modal save error: " . $error;
                }
                
                $countAll = $countAll + 1;
            } catch (\Throwable $th) {
                array_push($failed_array, "data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because there are something error!");
            }
        }
            
        //check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
            $data_error = implode("\n",array_unique($failed_array));
        }else{
            $failed_data = "";
            $data_error = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully approved ' . $countAll . ' BA data with Success ' . $countAll . ' data and Failed ' . $countFailed . ' data '); 

        //cek ada lagi data approval atau tidak
        if(Model::countApprovalManagerBA() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['approval-manager-ba', 'data_error' => $data_error]);
        }
    }
    
    public function actionApprovedall(){
        $id_app_data = yii::$app->request->get('id_approval');
        $id_app = explode(",",$id_app_data);
        
        //get data detail
        $data_ba = EskBeritaAcaraDetailOther::find()->where(['in','id',$id_app])->each();

        //inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();

        foreach($data_ba as $ba){
           //get data esk_lists_temp
            $esk_lists_temp = EskListsTemp::find()->where(['id_ba_detail' => $ba->id])->one();
            if(!empty($esk_lists_temp)){
                //push esk_temp ke esk_lists
                $esk_lists = new EskLists();
                $esk_lists->attributes = $esk_lists_temp->attributes;
                $esk_lists->old_position = $esk_lists_temp->old_position;
                $esk_lists->new_position = $esk_lists_temp->new_position;
                $esk_lists->old_area = $esk_lists_temp->old_area;
                $esk_lists->new_area = $esk_lists_temp->new_area;
                // add by faqih
                $esk_lists->nik_new_atasan = $data_ba_new->nik_new_atasan;
                $esk_lists->tunjangan_hot_skill = $data_ba_new->tunjangan_hot_skill;
                $esk_lists->tunjangan_aktualisasi = $data_ba_new->tunjangan_aktualisasi; // sprint 3 
                $esk_lists->dpe_length = $data_ba_new->dpe_length;
                $esk_lists->dpe_unit = $data_ba_new->dpe_unit;
                $esk_lists->grade = $data_ba_new->grade;
                    // sprint 3
                $esk_lists->new_nik = $data_ba_new->new_nik;
                $esk_lists->notif_stat_date = $data_ba_new->notif_stat_date;
                $esk_lists->leaving_reason = $data_ba_new->leaving_reason;
                        // end
                // end

                //get data band position 
                $databp = explode(".",$esk_lists_temp->new_bp);                

                //get id esk master
                $esk_template_master = EskTemplateMaster::findOne($esk_lists_temp->id_template_master);
                //$id_approval = EskApprovalMaster::find()->where(['band' => $databp[0], 'authority_area' => $esk_lists_temp->authority])->andWhere('directorate like "%'.$esk_lists_temp->old_directorate.'%"')->one();
                if(is_null($databp[0]) == true || $databp[0] == "" || $databp[0] == 0 || empty($databp[0]) && ($esk_lists_temp->level_band != "" || $esk_lists_temp->level_band !=0)) {
                    $id_approval = EskApprovalMaster::find()->where(['band' => $esk_lists_temp->level_band])->andWhere('authority_area like "%'.$esk_lists_temp->authority.'%"')->andWhere('directorate like "%'.$esk_lists_temp->old_directorate.'%"')->one();
                } else {
                    $id_approval = EskApprovalMaster::find()->where(['band' => $databp[0]])->andWhere('authority_area like "%'.$esk_lists_temp->authority.'%"')->andWhere('directorate like "%'.$esk_lists_temp->old_directorate.'%"')->one();
                }
                
                
                if(!empty($id_approval)){
                    $esk_lists->id_approval = $id_approval->id;
                    if($esk_lists->save()){
                        //update berita acara detail flag
                        $ba_detail = EskBeritaAcaraDetailOther::findOne($ba->id);
                        $ba_detail->flag_esk = 1;
                        $ba_detail->status = "Approved by BA Approval";
                        $ba_detail->save();

                        //=== save approval esk start ===// 
                        $data_approval = EskApprovalDetail::find()->where(['id_approval_master' => $id_approval->id])->all();
                        // bug fixing approval mpp - 12092024 -ejes
                        // >> if($esk_lists->level_band == 4 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false || strpos(strtolower($esk_lists->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false && $model->level_band <= 4)) {
                        if( ( $esk_lists->level_band == 4 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false 
                            || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false ) ) 
                            ||  ( strpos(strtolower($esk_lists->tipe), "sakit berkepanjangan") !== FALSE || strpos(strtolower($esk_lists->tipe), "mpp") !== FALSE)
                            )
                        {
                                // PJS
                                $data_a = new EskApprovalLists();
                                $data_a->id_esk         = $esk_lists->id;
                                $data_a->approval_nik   = '7310004';
                                $data_a->approval_name  = 'Indrawan Ditapradana';
                                $data_a->approval_mail  = 'indrawan_ditapradana@telkomsel.co.id';
                                $data_a->approval_title = 'Director Human Capital Management';
                                $data_a->sequence       = 1;
                                $data_a->save();
                        // bug fixing approval mpp - 12092024 -ejes
                        // >> } elseif($esk_lists->level_band >= 5 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false || strpos(strtolower($esk_lists->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($esk_lists->tipe), "mpp") !== false && $model->level_band >= 5)) {
                        } elseif($esk_lists->level_band >= 5 && (strpos(strtolower($esk_lists->tipe), "pejabat sementara") !== false || strpos(strtolower($esk_lists->tipe), "mutasi aps") !== false || strpos(strtolower($esk_lists->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($esk_lists->tipe), "mpp") !== false)) {
                                $data_a = new EskApprovalLists();
                                $data_a->id_esk         = $esk_lists->id;
                                $data_a->approval_nik   = '7610001';
                                $data_a->approval_name  = 'Nugroho';
                                $data_a->approval_mail  = 'nugroho@telkomsel.co.id';
                                $data_a->approval_title = 'President Director';
                                $data_a->sequence       = 1;
                                $data_a->save();
                        } else {
                            foreach($data_approval as $approval){
                                $data_a = new EskApprovalLists();
                                $data_a->id_esk = $esk_lists->id;
                                $data_a->approval_nik = $approval->nik;
                                $data_a->approval_name = $approval->employee->nama;
                                $data_a->approval_mail = $approval->employee->email;
                                $data_a->approval_title = $approval->employee->title;
                                $data_a->sequence = $approval->sequence;
                                $data_a->save();
                            }
                        }

                        $approval_data = EskApprovalLists::find()->where(['id_esk' => $esk_lists->id])->one();
                        $model2 = EskLists::findOne($esk_lists->id);
                        $model2->flag_approval_seq = 1;
                        $model2->status = "processed";
                        $model2->tracking = "Awaiting approval of ".$approval_data->approval_title;
                        $model2->save();
                        //var_dump($model2->getErrors(),$model2->id);exit;
                        //=== save ack esk start ===//
                        $this->setAcknowlegeLists($esk_template_master->flag_deliver_to, $esk_lists->nik, $esk_lists->old_position, $esk_lists->new_position, $esk_lists->new_position_id, $esk_lists->authority, $esk_lists->id, $esk_lists->new_directorate);
                        //=== save ack esk end ===//

                        //save workflow esk action "Pembuatan eSK oleh Drafter (nama karyawan login)"
                        Model::setWorkFlow($esk_lists->id,"Persetujuan Berita Acara dan pembuatan eSK oleh ".$ba->beritaAcaras->createdBy->nama." - ".$ba->beritaAcaras->createdBy->title."","-");

                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Generate eSK with ID ".$esk_lists->id);

                        $countSuccess = $countSuccess + 1;
                    }else{
                        $countFailed = $countFailed + 1;
                        $error = implode(",",$esk_lists->getErrorSummary(true));
                        array_push($failed_array,"data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because ".$error);
                    }
                }else{
                    $countFailed = $countFailed + 1;
                    array_push($failed_array,"data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because approval data that matches type of esk not found.");
                }
            }else{
                $countFailed = $countFailed + 1;
                array_push($failed_array,"data BA ".$ba->nik."/".$ba->nama."/".$ba->tipe." failed because eSK data not found, please reject and recreate BA!");
            }

            $countAll = $countAll + 1;
        }
            
        //check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
            $data_error = implode("\n",array_unique($failed_array));
        }else{
            $failed_data = "";
            $data_error = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully approved ' . $countAll . ' BA data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '); 

        //cek ada lagi data approval atau tidak
        if(Model::countApprovalBA() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['approval-ba', 'data_error' => $data_error]);
        }
    }

    public function actionModalrejected(){
        $id = yii::$app->request->get('id');

        return $this->renderAjax('reject_dialog',[
            "id" => $id 
        ]);
    }

    // ejesdemo 03102024 -- dua
    public function actionModalverification(){
        $id = yii::$app->request->get('id');

        return $this->renderAjax('app_dialog_verification',[
            "id" => $id
        ]);
    }    
    
    public function actionRejectedall(){
        $id_app_data = yii::$app->request->get('id_app_rejected');
        $id_app = explode(",",$id_app_data);
        $remark = yii::$app->request->get('remark');

        //inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();

        foreach($id_app as $id_app){
            //get id master 
            $data_detail = EskBeritaAcaraDetailOther::findOne($id_app);
            $data_detail->flag_esk = 2;
            $data_detail->flag_ba_manager = 0;

            if($data_detail->save()){
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Rejected BA data with ID ".$data_detail->id);

                //send mail notifikasi ke creator BA

                //set success count
                $countSuccess = $countSuccess + 1;
            }else{
                //set failed count
                $countFailed = $countFailed + 1;

                //logging data
                $error = implode(",",$data_detail->getErrorSummary(true));
                array_push($failed_array,"data BA ".$data_detail->nik."/".$data_detail->nama."/".$data_detail->tipe." failed rejected BA because ".$error);
                Model::saveLog(Yii::$app->user->identity->username, "Failed rejected eSK data for ID BA Detail".$data_detail->id." because ".$error);
            }
            
            //count iteration
            $countAll = $countAll + 1;
        }
        
        //check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
            $data_error = implode("\n",array_unique($failed_array));
        }else{
            $failed_data = "";
            $data_error = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully rejected ' . $countAll . ' BA data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '); 

        //cek ada lagi data approval atau tidak
        if(Model::countApprovalBA() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['approval-ba', 'data_error' => $data_error]);
        }
    }

    /**
     * Displays a single BeritaAcara model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        if ($stat = Yii::$app->request->post('status')) {
            $model->status = $stat;
            if($model->save())
                return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    public function actionViewBeritaAcara($id)
    {
        $model = EskBeritaAcaraOther::findOne($id);
        $data_detail = EskBeritaAcaraDetailOther::beritaAcaraDetailData($id);

        return $this->render('view_ba', [
            'model' => $model,
            'detail' => $data_detail
        ]);
    }

    /**
     * Creates a new BeritaAcara model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new BeritaAcara();
        $model->ba_date = date("Y-m-d");
        $employee = Yii::$app->user->identity->employee;
        
        if ($model->load(Yii::$app->request->post())) {
            $model->category_number = "/e-SK.01/HB-01/".EvaluationData::bulanRomawi()."/".date("Y");
            $model->no = $model->numberEvaluation. $model->category_number;
            $model->area = $employee->area;
            $model->directorate = $employee->directorate;
            $model->tipe = 'Evaluasi';
            
            if($model->save())
                return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionCreateBeritaAcara(){
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        
        //throw new NotFoundHttpException('Halaman ini adalah halaman Development, mohon masuk ke ESK menggunakan portal HCM : hcm.telkomsel.co.id');
        
        $model = new EskBeritaAcaraOther();
        if (Yii::$app->request->post()) {
            //get data master
            $request = Yii::$app->request->post();
            $batch_number = $request['batch_number'];
            $directorate = $request['directorate'];
            $tipe = $request['tipe'];
            $ba_date = date("Y-m-d",strtotime($request['ba_date']));
            $number_ba = $request['ba_no'];
            $area = (empty(Yii::$app->user->identity->employee)) ? "HEAD OFFICE" : Yii::$app->user->identity->employee->area;
            $created_by = (empty(Yii::$app->user->identity->employee)) ?  "308665" : Yii::$app->user->identity->employee->person_id;
            //$status = 2;
            $status = $request['status'];
            $data_number = Model::getNumberBa($area,$number_ba);

            //set sikom_berita_acara
            $model->sequence = $data_number['sequence'];
            $model->month_id = $data_number['month'];
            $model->year_id = $data_number['year'];
            $model->no_ba = $data_number['no_sikom'];
            $model->no = $data_number['no_full'];
            $model->area = $area;
            $model->directorate = $directorate;
            $model->ba_date = $ba_date;
            $model->tipe = $tipe;
            $model->status = $status;
            $model->created_by = $created_by;
            $model->approved_by = $this->getHeadCreator();
            
            //get data employee
            $data_emp = EskBeritaAcaraDetailOtherTemp::find()->where(['batch_number' => $batch_number])->all();

            //save data master
            $transaction = \Yii::$app->db->beginTransaction();
            try {
                if ($model->save()) {                    
                    //save data detail to berita_acara_detail
                    if(!empty($data_emp)){
                        $detail_mail_ba = array();
                        foreach($data_emp as $employee){
                            $ba_detail = new EskBeritaAcaraDetailOther();
                            $ba_detail->id_master = $model->id;
                            $ba_detail->attributes = $employee->attributes;
                            $ba_detail->status = "Waiting of BA Approval";
                            if(!empty($employee->keterangan_ba_1) && strpos($employee->keterangan_ba_1,"{nomor_pembuatan_ba}") !== false){
                                $replace_data = str_replace("{nomor_pembuatan_ba}",$model->no,$employee->keterangan_ba_1);
                                $ba_detail->keterangan_ba_1 = str_replace("{tanggal_pembuatan_ba}",Model::TanggalIndo(date('Y-m-d')),$replace_data);
                            }
                            if (!$ba_detail->save()) {
                                $transaction->rollBack();
                                Yii::$app->session->setFlash('error', "Berita Acara data was not created.");
                            }else{
                                //update esk_lists_temp
                                $esk_lists_temp = EskListsTemp::find()->where(['id_ba_detail' => $employee->id])->one();
                                $esk_lists_temp->id_ba_detail = $ba_detail->id;
                                $esk_lists_temp->nomor_ba = $model->no;
                                $esk_lists_temp->ba_date = $model->ba_date;
                                $esk_lists_temp->keterangan_ba_1 = $ba_detail->keterangan_ba_1;
                                if(!$esk_lists_temp->save()){
                                    $transaction->rollBack();
                                    Yii::$app->session->setFlash('error', "Berita Acara data was not created.");
                                }
                                /*
                                else{
                                    //regenerate eSK
                                    echo "masukkah?";exit;
                                    $this->regenerateEsk($ba_detail->id,"1");
                                }
                                */
                                
                                $delete_ba_temp = EskBeritaAcaraDetailOtherTemp::deleteAll('batch_number = "'.$batch_number.'"');

                                //set detail data for email to approval
                                array_push($detail_mail_ba, '
                                    <tr>
                                        <td>'.$ba_detail->nik.'</td>
                                        <td>'.$ba_detail->nama.'</td>
                                        <td>'.$ba_detail->old_title.'</td>
                                        <td>'.$ba_detail->new_title.'</td>
                                        <td>'.$ba_detail->tipe.'</td>
                                    </tr>
                                ');
                            }
                        }

                        //cek apakah submit jika ya proses 
                        if($status == "1" || $status == 1){
                            //send notifikasi ke atasan creator/drafter
                            $subject = "[eSK] Approval of Berita Acara Number ".$model->no."";
                            $to = $model->approvedBy->email;
                            $content = $this->renderPartial('../../mail/mail-approval-ba',['data_master' => $model, 'data_detail' => $detail_mail_ba, 'nama' => $model->approvedBy->nama],true);
                            Model::sendMailOne($to,$subject,$content);
                        }
                    }

                    $transaction->commit();

                    Yii::$app->session->setFlash('success', "Berita Acara data successfully created."); 

                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Create Berita Acara data with ID ".$model->id);
                }else{
                    $transaction->rollBack();
                    Yii::$app->session->setFlash('error', "Berita Acara data was not created.");
                }
            } catch (Exception $e) {
                // penyimpanan gagal, rollback database transaction
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', "Berita Acara data was not created because ".$e);
            }
            
            return $this->redirect(['index']);
        }

        //get data lainnya
        $batch_number = (empty(yii::$app->request->get('batch_number'))) ? Model::generateRandomString() : yii::$app->request->get('batch_number');
        
        return $this->render('create_ba', [
            'model' => $model,
            'batch_number' => $batch_number
        ]);
    }

    public function getHeadCreator(){  
        $atasan_array = Model::getHead(Yii::$app->user->identity->employee->nik);
        $band = Yii::$app->user->identity->employee->band;
        $atasan = implode(";",$atasan_array);

        if(!empty($atasan_array)){
            foreach($atasan_array as $emp){
                $data_emp = Employee::find()->where(['nik' => $emp])->one();
                if(
                    (strpos(strtolower($data_emp->job_category), "general manager") !== false && $band <= 3 ) ||
                    (strpos(strtolower($data_emp->job_category), "vice president") !== false && $band == 4) ||
                    (strpos(strtolower($data_emp->job_category), "director") !== false && $band >= 5)
                ){
                    $vp_nik = $emp;
                    break;
                }else{
                    $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
                }
            }
        }else{
            $data_emp = Employee::find()->where(['nik' => $emp])->one();
            $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
        }

        return $vp_nik;
    }
    

    public function actionImportEmployeeUpdate($batch_number,$temp = null)
    {   
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $data_error = "";

        if (Yii::$app->request->post()) {
            if($filedata = UploadedFile::getInstanceByName('file_name')){
                $data = Excel::import($filedata->tempName, [
                    'setFirstRecordAsKeys' => true,
                    'setIndexSheetByName' => true,
                    'getOnlySheet' => 'Sheet1',
                ]);

                //inisialisasi data count 
                $countSuccess = 0;
                $countFailed = 0;
                $countAll = 0;
                $failed_array = array();
                $i = 2;

                foreach ($data as $item) {
                    if(
                        isset($item['nik'])
                    ){
                        //get data
                        $nik =  trim(preg_replace('/\s\s+/', ' ',$item['nik']));
                        $nama =  trim(preg_replace('/\s\s+/', ' ',$item['nama']));
                        $title  = $item['title'];
                        $category  = $item['employee_category'];
                        $organization  = $item['organization'];
                        $band = trim(preg_replace('/\s\s+/', ' ',$item['band']));
                        $city = $item['kota'];
                        $married = trim(preg_replace('/\s\s+/', ' ',$item['status_pernikahan']));
                        $start_date = trim(preg_replace('/\s\s+/', ' ',$item['start_date_assigment']));
                        $admins = trim(preg_replace('/\s\s+/', ' ',$item['admins']));
                        $section = trim(preg_replace('/\s\s+/', ' ',$item['section']));
                        $dept = trim(preg_replace('/\s\s+/', ' ',$item['department']));
                        $division = trim(preg_replace('/\s\s+/', ' ',$item['division']));
                        $bgroup = trim(preg_replace('/\s\s+/', ' ',$item['bgroup']));
                        $egroup = trim(preg_replace('/\s\s+/', ' ',$item['egroup']));
                        $subdir = trim(preg_replace('/\s\s+/', ' ',$item['directorate']));
                        $bp = trim(preg_replace('/\s\s+/', ' ',$item['bp']));
                        $bi = trim(preg_replace('/\s\s+/', ' ',$item['bi']));
                        $salary = trim(preg_replace('/\s\s+/', ' ',$item['salary']));
                        $tunjangan = trim(preg_replace('/\s\s+/', ' ',$item['tunjangan']));
                        $tunjangan_jab = trim(preg_replace('/\s\s+/', ' ',$item['tunjangan_jabatan']));
                        $tunjangan_rekom = trim(preg_replace('/\s\s+/', ' ',$item['tunjangan_rekom']));
                        $dpe = trim(preg_replace('/\s\s+/', ' ',$item['dpe']));
                        $job_cat = trim(preg_replace('/\s\s+/', ' ',$item['job_category']));
                        $tgl_masuk = trim(preg_replace('/\s\s+/', ' ',$item['tanggal_masuk']));
                        $structural = trim(preg_replace('/\s\s+/', ' ',$item['structural']));
                        $functional = trim(preg_replace('/\s\s+/', ' ',$item['functional']));

                        //get data employee 
                        $employee = Employee::find()->where(['nik' => $nik])->one();

                        if(empty($employee)){
                            //get last person_id 
                            $connection = Yii::$app->getDb();
                            $command = $connection->createCommand("
                            SELECT person_id FROM employee ORDER BY person_id+0 DESC LIMIT 1    
                            ");
                            $person_id = $command->queryAll();
                            //$person_id = Employee::find()->select('person_id')->orderBy('person_id+0 DESC')->one();
                            if(empty($person_id)){
                                continue;
                            }
                            
                            //save data emp baru
                            $model = new Employee();
                            $model->person_id = (string)($person_id[0]['person_id']+1);
                            $model->nik = $nik;
                            $model->nama = $nama;          
                            $model->tanggal_masuk = $tgl_masuk;  
                            $model->tgl_masuk = $tgl_masuk;  
                        }else{
                            //updata data emp
                            $model = Employee::find()->where(['nik' => $nik])->one();
                        }

                        $model->title = $title;
                        $model->employee_category = $category;
                        $model->organization = $organization;
                        $model->band = (empty($band) || $band == "-") ? null : $band;
                        $model->kota = $city;
                        $model->status_pernikahan = $married;
                        $model->start_date_assignment = $start_date;
                        $model->admins = $admins;
                        $model->section = (empty($section) || $section == "-") ? null : $section;
                        $model->department = (empty($dept) || $dept == "-") ? null : $dept;
                        $model->division = (empty($division) || $division == "-") ? null : $division;
                        $model->bgroup = (empty($bgroup) || $bgroup == "-") ? null : $bgroup;
                        $model->egroup = (empty($egroup) || $egroup == "-") ? null : $egroup;
                        $model->directorate = $subdir;
                        $model->bp = (empty($bp) || $bp == "-") ? null : $bp;
                        $model->bi = (empty($bi) || $bi == "-") ? null : $bi;
                        $model->salary = (empty($salary) || $salary == "-") ? null : $salary;
                        $model->tunjangan = (empty($tunjangan) || $tunjangan == "-") ? null : $tunjangan;
                        $model->tunjangan_jabatan = (empty($tunjangan_jab) || $tunjangan_jab == "-") ? null : $tunjangan_jab;
                        $model->tunjangan_rekomposisi = (empty($tunjangan_rekom) || $tunjangan_rekom == "-") ? null : $tunjangan_rekom;
                        $model->dpe = (empty($dpe) || $dpe == "-") ? null : $dpe;
                        $model->job_category = (empty($job_cat) || $job_cat == "-") ? null : $job_cat;
                        $model->last_update_date = date("Y-m-d H:i:s");
                        $model->structural = (empty($structural) || $structural == "-") ? null : $structural;
                        $model->functional = (empty($functional) || $functional == "-") ? null : $functional;

                        if($model->save()){
                            $countSuccess = $countSuccess + 1; 
                        }else{
                            //set failed count
                            $countFailed = $countFailed + 1;

                            //logging data
                            $error = implode(",",$model->getErrorSummary(true));
                            array_push($failed_array,"data Employee for row ".$i." (".$item['nik']."/".$item['nama'].") failed because ".$error);
                        }

                        //count iteration
                        $i++;
                        $countAll = $countAll + 1;
                    }else{
                        //set failed count
                        $countFailed = $countFailed + 1;
                                    
                        //logging data
                        array_push($failed_array,"data Employee for row ".$i." failed because some mandatory field is empty!");
                    }
                }

                if(!empty($failed_array)){
                    $failed_data = "that is ".implode(", ",array_unique($failed_array));
                }else{
                    $failed_data = "";
                }

                //set flash message
                Yii::$app->session->setFlash('info', 'Successfully import ' . $countAll . ' data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '); 

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Import data of Employee");

                //export error to file text
                if(!empty($failed_data)){
                    $data_error = implode("\n",array_unique($failed_array));
                }    
            }else{
                Yii::$app->session->setFlash('error', "Not found file, please upload again or call administrator!");
            }

            $file_open = 'create_ba';
            $model_ba = new EskBeritaAcaraOther();

            return $this->render($file_open, [
                'model' => $model_ba,
                'batch_number' => $batch_number,
                'data_error' => $data_error,
                'flag_reject' => $flag_reject
            ]);

        }

        return $this->renderAjax('upload');
    }

    public function actionTesaja()
    {
        $model = new Employee;
        
        $model->isPensiun();
    }
    

    public function actionImportEmployee($batch_number,$temp = null)
    {   
        set_time_limit(0);
        ini_set('memory_limit', '3048M');
        
        $data_error = "";

        if (Yii::$app->request->post()) {
            if($filedata = UploadedFile::getInstanceByName('file_name')){
                $data = Excel::import($filedata->tempName, [
                    'setFirstRecordAsKeys' => true,
                    'setIndexSheetByName' => true,
                    'getOnlySheet' => 'Sheet1',
                ]);

                //inisialisasi data count 
                $countSuccess = 0;
                $countFailed = 0;
                $countAll = 0;
                $failed_array = array();
                $i = 2;
   
                foreach ($data as $item) {
                    if(
                        isset($item['TIPE_BA']) &&  
                        isset($item['NIK']) && isset($item['EFFECTIVE_DATE']) &&
                        isset($item['NEW_POSITION']) && isset($item['NEW_ORGANIZATION']) &&
                        isset($item['NEW_CITY'])
                    ){
                        
                        //get data
                        $nik =  trim(preg_replace('/\s\s+/', ' ',$item['NIK']));
                        $ba_date = $item['EFFECTIVE_DATE'];
                        $tipe = trim(preg_replace('/\s\s+/', ' ',$item['TIPE_BA']));
                        $code = trim(preg_replace('/\s\s+/', ' ',$item['CODE_TEMPLATE']));
                        $new_bi = trim(preg_replace('/\s\s+/', ' ',$item['NEW_BI']));
                        $nota_dinas = $item['NOTA_DINAS'];
                        $periode = $item['PERIODE'];
                        $nama_penyakit = $item['DISEASES_NAME'];
                        $nominal_insentif = $item['INSENTIF_AMOUNT'];
                        $kd_1 = $item['KD_1'];
                        $kd_2 = $item['KD_2'];
                        $kd_3 = $item['KD_3'];
                        $ba_1 = $item['BA_1'];
                        $ba_2 = $item['BA_2'];
                        $ba_3 = $item['BA_3'];
                        $cltp_reason = $item['CLTP_REASON'];
                        $start_sick = $item['START_SICK_DATE'];
                        $end_sick = $item['END_SICK_DATE'];
                        $phk_date = $item['PHK_DATE'];
                        $statement_date = $item['STATEMENT_DATE'];
                        $last_payroll = $item['LAST_PAYROLL'];
                        $resign_date = $item['RESIGN_DATE'];
                        $scholarship_program = $item['SCHOLARSHIP_PROGRAM'];
                        $scholarship_university = $item['SCHOLARSHIP_UNIVERSITY'];
                        $scholarship_level = $item['SCHOLARSHIP_LEVEL'];
                        $flag_kisel = $item['MEMBER_KISEL'];
                        $old_position = $item['OLD_POSITION'];
                        $new_position = $item['NEW_POSITION'];
                        $new_organization  = $item['NEW_ORGANIZATION'];
                        $new_city = $item['NEW_CITY'];
                        $new_bp = trim(preg_replace('/\s\s+/', ' ',$item['NEW_BP']));
                        $gaji_dasar_nss = $item['GAJI_DASAR_BSS'];
                        $tbh_nss = $item['TBH_BSS'];
                        $tunjangan_rekomposisi_nss = $item['TUNJANGAN_REKOMPOSISI_BSS'];
                        $tunjab_nss = $item['TUNJAB_BSS'];
                        // add by faqih
                        $nik_new_atasan =  trim(preg_replace('/\s\s+/', ' ',$item['ATASAN_BARU']));
                        $tunjangan_hot_skill = $item['TUNJANGAN_HOT_SKILL'];
                        $tunjangan_aktualisasi = $item['TUNJANGAN_AKTUALISASI_HOT_SKILL']; // sprint 3
                        //$tunjangan_aktualisasi = $item['TUNJANGAN_AKTUALISASI_HOT_SKILL']; // ditake out --Tirta 10-09-2024

                        $grade =  trim(ucwords(preg_replace('/\s\s+/', ' ',$item['GRADE'])));
                        $dpe_length = trim(preg_replace('/\s\s+/', ' ',$item['DPE_LENGTH']));
                        $dpe_unit = trim(preg_replace('/\s\s+/', ' ',$item['DPE_UNIT']));
                        // end

                        // validasi DPE add by faqih
                        if(empty($dpe_length) && !empty($dpe_unit)){
                            $countFailed = $countFailed + 1;

                            array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because value DPE Length is null while the value of DPE Unit is there .");
                            continue;
                        }else if(!empty($dpe_length) && empty($dpe_unit)){
                            $countFailed = $countFailed + 1;

                            array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because value DPE Unit is null while the value of DPE Length is there .");
                            continue;
                        }
                        // end

                        // add by faqih sprint 3
                        $notif_stat_date = $item['STATEMENT_DATE'];
                        $new_nik = trim(preg_replace('/\s\s+/', ' ',$item['NEW_NIK']));
                        $leaving_reason = trim($item['LEAVING_REASON']);
                        // end

                        $datakar = Employee::findOne(['nik' => $nik]);
                        if(!empty($datakar)) {
                            $data_kr_bss = EskCodeParam::findOne(['band' => $datakar->band, 'directorate' => $datakar->directorate, 'code' => '{kr_organisasi_bss}']);
                            if(!empty($data_kr_bss)) {
                                $kr_bss = $data_kr_bss->value;
                            }
                        } 
                        
                        $level_gaji     = $item['LEVEL_GAJI'];
                        $level_posisi   = $item['LEVEL_POSISI'];
                        
                        //get data employee 
                        $employee = Employee::find()->where(['nik' => $nik])->one();

                        // add validasi nik atasan dan grade by faqih
                        if(!empty($nik_new_atasan)){
                        //     $employeeAtasan = Employee::find()->where(['nik' => $nik_new_atasan])->one();
                        //     if($employeeAtasan->band < 3){
                        //         $countFailed = $countFailed + 1;
                        //         //logging data
                        //         array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because NIK ATASAN BARU has a band smaller than 3 (Band at least greater than 3)");
                        //         continue; 
                        //     }

                            // add by faqih note frs add validsai nik != nik_new_atasan 26042024
                            if($nik_new_atasan == $nik){
                                $countFailed = $countFailed + 1;
                                //logging data
                                array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because NIK ATASAN BARU cannot be the same as NIK");
                                continue; 
                            }

                        }


                        //check value grade by faqih
                        $sql = "SELECT * FROM TSEL_HR_GRADE_V WHERE grade = '".$grade."'";
                        $conn = Yii::$app->dbOra;
                        $commandOra = $conn->createCommand($sql)
                        ->queryOne();

                        if(empty($commandOra)){
                            $countFailed = $countFailed + 1;

                            array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because value (".$grade.") Of Grade data is not in EBS.");
                            continue;
                        }
                        // else{
                        //     if(strpos($grade, '.') == false){

                        //         $countFailed = $countFailed + 1;

                        //         array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because value (".$grade.") of the grade is not float or decimal (.) please check the format of the column, it should be (General or Text) not CUSTOM type");
                        //         continue; 
                        //     }
                        // }
                        
                        //check value tunjangan hot skill by tirta - 10-09-2024
                        //remark temporary for approval esk enhancement
                        /*
                        if(!empty($tunjangan_hot_skill)){ //added ejes not mandatory
                            $check_tunjangan_hot_skill = "SELECT DISTINCT(level_hotskill) FROM TUNJANGAN_HOT_SKILL_V WHERE level_hotskill = '".$tunjangan_hot_skill."'";
                            $commandOra = $conn->createCommand($check_tunjangan_hot_skill)
                            ->queryOne();

                            if(empty($commandOra)){
                                $countFailed = $countFailed + 1;

                                array_push($failed_array,"Data Tunjangan Hot Skill for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because value (".$tunjangan_hot_skill.") Of Hot Skill Allowance Data is not in EBS, this value should be 0 until 3.");
                                continue;
                        }
                        }*/


                        //check position by faqih
                        $new_position_data = Position::find()->where(['nama' => trim($new_position)])->andWhere("(position_code iS NOT NULL or position_code <> '') and status = 1")->one();
                        if(empty($new_position_data)){
                            $countFailed = $countFailed + 1;
                            array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because detail new position data on HC Portal is not found\r\n");
                            continue;
                        }

                        // 

                        //get data posisi tujuan
                        if(strpos($new_position,"Senior Staff") !== false || strpos($tipe,"Exchange") !== false){
                            if (strpos($employee->band, '\\') !== FALSE) {
                                $check_band = "";
                            }else{
                                $check_band = trim(preg_replace('/\s\s+/', ' ',$employee->band));
                            }

                            //check org
                            $check_position = Position::find()->where('nama = "'.$new_position.'" AND organization = "'.$new_organization.'" AND LOWER(desc_city) = "'.strtolower($new_city).'" AND (position_code iS NOT NULL or position_code <> "") AND status = 1 AND band IS NOT NULL AND directorate IS NOT NULL')->one();
                            if(empty($check_position)) {
                                $check_position = Position::find()->where('nama = "Senior Staff" AND organization = "'.$new_organization.'" AND LOWER(desc_city) = "'.strtolower($new_city).'" AND band >= "'.$check_band.'" AND (position_code iS NOT NULL or position_code <> "") AND status = 1 AND band IS NOT NULL AND directorate IS NOT NULL')->one();
                            }
                            //var_dump($new_organization,$new_city,$employee->band);exit;
                            if(!empty($check_position)){
                                if(strpos($new_position,"Senior Staff") !== false){
                                    $position_id = "Senior Staff";
                                    $position_id_org = $check_position->id;
                                }else{
                                    $position_id = $check_position->id;
                                    $position_id_org = "";
                                }
                            }else{
                                //not exist data organization continue iteration
                                $countFailed = $countFailed + 1;
                                        
                                //logging data
                                array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because organization data not found!");
                                continue;
                            }
                        }else{
                            if (strpos($item['NEW_POSITION'], 'Pj.') !== false) {
                                $new_position = trim($item['NEW_POSITION'],"Pj. ");
                            }
                            
                            $check_position = Position::find()->where('nama = "'.$new_position.'" AND organization = "'.$new_organization.'" AND LOWER(desc_city) = "'.strtolower($new_city).'" AND (position_code iS NOT NULL or position_code <> "") AND status = 1 AND band IS NOT NULL AND directorate IS NOT NULL')->one();
                            if(!empty($check_position)){
                                $position_id = $check_position->id;
                                $position_id_org = "";
                            }else{
                                //not exist data organization continue iteration
                                $countFailed = $countFailed + 1;
                                        
                                //logging data
                                array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because position data not found!");
                                continue;
                            }
                        }                        
                        

                        //check duplicate position by batch number
                        if($temp == null){
                            $data_ba_exist = EskBeritaAcaraDetailOtherTemp::find()->where(['batch_number' => $batch_number,'nik' => $nik, 'tipe' => $tipe])->one();
                            $rollback_update = EskBeritaAcaraDetailOtherTemp::find()->where(['batch_number' => $batch_number,'nik' => $nik, 'tipe' => $tipe])->one();
                            $model = new EskBeritaAcaraDetailOtherTemp();
                            $model->batch_number = $batch_number;
                            $flag_reject = 0;
                            
                        }else{
                            $data_ba_exist = EskBeritaAcaraDetailOther::find()->where(['id_master' => $batch_number,'nik' => $nik, 'tipe' => $tipe])->one();
                            $rollback_update = EskBeritaAcaraDetailOtherTemp::find()->where(['batch_number' => $batch_number,'nik' => $nik, 'tipe' => $tipe])->one();
                            $model = new EskBeritaAcaraDetailOther();
                            $model->id_master = $batch_number;
                            $flag_reject = ($data_ba_exist->flag_esk == 2) ? 1 : 0;
                            
                        }
                        $data_ba_old = $data_ba_exist;

                        //get data position
                        if(strpos($position_id,"Senior Staff") !== false ){ //|| strpos($tipe,"Exchange") !== false){
                            $position = Position::find()->where(['id' => $position_id_org])->one();
                            $position_id_baru = $position_id_org;
                            $kode_kota_baru = $check_position->city;
                            $kota_baru = $check_position->desc_city;
                        }else{
                            $position = Position::find()->where(['id' => $position_id])->one();
                            $position_id_baru = $position_id;
                            $kode_kota_baru = $position->city;
                            $kota_baru = $position->desc_city;
                        }
                        
                        //validasi senior staff
                        if(strpos($position_id,"Senior Staff") !== false && !empty($position_id)){
                            //replace posisi
                            $position_baru = Model::replaceSeniorStaff($position->organization);
                            $is_senior_staff = 1;
                        }else{
                            //cek apakah tipe Exchange
                            if(strpos($tipe,"Exchange") !== false){
                                $position_baru = $new_position;
                            }else{
                                $position_baru = $position->nama;
                            }
                            $is_senior_staff = 0;
                        }

                        //validasi posisi lama dan tipe from Exchange
                        if(strpos($tipe,"from Exchange") !== false && !empty($old_position)){
                            $position_lama = $old_position;
                        }else{
                            $position_lama = $employee->title;
                        }
                        
                        //validasi code template 
                        if(empty($code)){
                            //search code template
                            $new_city_data = $kode_kota_baru.";".$kota_baru;
                            $getCodeTemplate = json_decode(Yii::$app->runAction('berita-acara/get-code-template', ['position_id' => $position_id_baru, 'nik' => $nik, 'new_city' => $new_city_data]));
                            if($getCodeTemplate->result == 0){
                                $countFailed = $countFailed + 1;
                                        
                                //logging data
                                array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because can't mapping BA data (".$getCodeTemplate->remark.")!");
                                continue;
                            }else{
                                $code = $getCodeTemplate->code_template;
                            }
                        }

                        //cek apakah codenya ada di esk_template_master
                        $cek_template = EskTemplateMaster::find()->where(['code_template' => $code])->one();
                        // update by faqih sprint 3
                        if(empty($cek_template)){
                            //not exist data organization continue iteration
                            $countFailed = $countFailed + 1;
                                        
                            //logging data
                            array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because code template of eSK not found!");
                            continue;
                        }else{
                            // $cekgroup_reason = EskGroupReasonData::find()->where(['id' => $cek_template->id_reason])->one();
                            $cekgroup_reason = EskGroupReasonData::findOne($cek_template->id_reason);

                            if(strpos($cekgroup_reason->group, 'Terminate') !== false ){
                                if(empty($notif_stat_date)){
                                     $countFailed = $countFailed + 1;
                                        
                                    //logging data
                                    array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because Group Reason for (".$cekgroup_reason->group.") field STATEMENT_DATE nust be insert!");
                                    continue;
                                }

                                if(empty($leaving_reason)){
                                     $countFailed = $countFailed + 1;
                                        
                                    //logging data
                                    array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because Group Reason for (".$cekgroup_reason->group.") field LEAVING_REASON nust be insert!");
                                    continue;
                                }else{
                                    $conn = Yii::$app->dbOra;
                                    $dataOra = $conn->createCommand("SELECT * FROM TSEL_HR_LEAVE_REASON_V WHERE MEANING = '".$leaving_reason."' ")
                                        ->queryAll();
                                    if(empty($dataOra)){
                                        $countFailed = $countFailed + 1;
                                        
                                        //logging data
                                        array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because Group Reason for (".$cekgroup_reason->group.") field LEAVING_REASON Not Available in EBS data");
                                        continue;
                                    }
                                }
                            }

                            if(strpos($cekgroup_reason->reason, 'Contract To Permanent') !== false){
                                if(empty($new_nik)){
                                    $countFailed = $countFailed + 1;
                                    
                                    //logging data
                                    array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because Reason for (".$cekgroup_reason->reason.") field NEW_NIK nust be insert!");
                                    continue;
                                }else{
                                    $datanik = Employee::findOne(['nik' => $new_nik]);
                                    if(!empty($datanik)) {
                                        $countFailed = $countFailed + 1;
                                
                                        array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because New NIK (".$new_nik.") for (".$cekgroup_reason->reason.") Already Available! ");
                                        continue;
                                    }
                                }
                            }
                        }
                        
                        //validasi additional content
                        $count_validasi = 0;
                        $validasiTemplate = json_decode(Yii::$app->runAction('esk-template-master/validation-content', ['code' => $code]));
                        if($validasiTemplate->flag_nama_penyakit == 1 && empty($nama_penyakit)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Diseases Name for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->periode == 1 && empty($periode)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Periode for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_nota_dinas == 1 && empty($nota_dinas)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Nota Dinas for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_nominal_insentif == 1 && empty($nominal_insentif)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Insentif Amount for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_ba_1 == 1 && empty($ba_1)){
                            $ba_1 = "Berita Acara Sidang Komite Karir PT Telekomunikasi Selular Nomor : {nomor_pembuatan_ba} tanggal {tanggal_pembuatan_ba}.";
                        }
                        if($validasiTemplate->flag_ba_2 == 1 && empty($ba_2)){
                            $count_validasi++;     
                            array_push($failed_array,"data of BA 2 for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_ba_3 == 1 && empty($ba_3)){
                            $count_validasi++;     
                            array_push($failed_array,"data of BA 3 for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_kd_1 == 1 && empty($kd_1)){
                            $count_validasi++;     
                            array_push($failed_array,"data of KD 1 for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_kd_2 == 1 && empty($kd_2)){
                            $count_validasi++;     
                            array_push($failed_array,"data of KD 2 for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_kd_3 == 1 && empty($kd_3)){
                            $count_validasi++;     
                            array_push($failed_array,"data of KD 3 for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_start_sick == 1 && empty($start_sick)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Start Date of Sick for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_end_sick == 1 && empty($end_sick)){
                            $count_validasi++;     
                            array_push($failed_array,"data of End Date of Sick for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_phk_date == 1 && empty($phk_date)){
                            $count_validasi++;     
                            array_push($failed_array,"data of PHK Date for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_statement_date == 1 && empty($statement_date)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Statement Date for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_last_payroll == 1 && empty($last_payroll)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Last Payroll for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_resign_date == 1 && empty($resign_date)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Resign Date for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_scholar_program == 1 && empty($scholarship_program)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Scholarship Program for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_scholar_university == 1 && empty($scholarship_university)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Scholarship University for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_scholar_level == 1 && empty($scholarship_level)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Scholarship Level for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_cltp_reason == 1 && empty($cltp_reason)){
                            $count_validasi++;     
                            array_push($failed_array,"data of CLTP Reason for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($validasiTemplate->flag_kisel == 1 && empty($flag_kisel)){
                            $count_validasi++;     
                            array_push($failed_array,"data of KISEL Member for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") must be insert! ");
                        }
                        if($check_position->band == 0 || empty($check_position->band)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Position Band/Level Posisi for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") is empty! ");
                        }
                        if($check_position->directorate == "" || empty($check_position->directorate)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Directorate for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") is empty! ");
                        }
                        if($employee->directorate == "" || empty($employee->directorate)){
                            $count_validasi++;     
                            array_push($failed_array,"data of Directorate in Table Employee/Karyawan for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") is empty! ");
                        }
                        
                        //check apakah ada error diadditional field
                        if($count_validasi > 0){
                            //not exist data organization continue iteration
                            $countFailed = $countFailed + 1;
                                        
                            //logging data
                            array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because some additional data is empty!");
                            continue;
                        }
                        
                        if(empty($data_ba_exist)){
                            //strpos($item['TIPE_BA'], 'Promosi') !== false  || 
                            if ((strpos($item['TIPE_BA'], 'Evaluasi') !== false || strpos($item['TIPE_BA'], 'Promosi') !== false || strpos($item['NEW_POSITION'], 'Pj. ') !== false || strpos($position_lama, 'Pj.') !== false) && $check_position->structural == 'Y' && $check_position->band >= 2) {
                                
                                if(strpos($item['TIPE_BA'], 'Evaluasi Final') !== false) {
                                    $position_baru = $item['NEW_POSITION'];
                                } else {
                                    $position_baru = 'Pj. ' . $item['NEW_POSITION'];
                                }
                            } elseif(strpos($item['NEW_POSITION'], 'Pj.') !== false) {
                                $position_baru = 'Pj. ' . $item['NEW_POSITION'];
                            }
                            
                            
                            
                            /*
                            if(intval($level_gaji) > intval((substr($employee->bi,0,1)))){
                                $position_baru = trim($position_baru,"Pj. ");
                            }
                            */
                            
                            
                            $model->person_id = $employee->person_id;
                            $model->nik = $nik;
                            $model->nama = $employee->nama;
                            $model->is_senior_staff = $is_senior_staff;
                            $model->old_position_id = $employee->position_id;
                            $model->new_position_id = $position_id_baru;
                            $model->old_title = $position_lama;
                            $model->old_area = $employee->area;
                            $model->old_bgroup = $employee->bgroup;
                            $model->old_bi = $employee->bi;
                            $model->old_bp = $employee->bp;
                            $model->old_department = $employee->department;
                            $model->old_directorate = $employee->directorate;
                            $model->old_division = $employee->division;
                            $model->old_egroup = $employee->egroup;
                            $model->old_kode_kota = $employee->kode_kota;
                            $model->old_kota = $employee->kota;
                            $model->old_organization = $employee->organization;
                            $model->old_section = $employee->section;
                            $model->old_region = $employee->admins;
                            $model->new_area = $position->area;
                            $model->new_bgroup = $position->grp;
                            $model->new_bp = (!empty($new_bp)) ? $new_bp : $position->bp;
                            if($level_gaji == "" || empty($level_gaji)) {
                                $model->new_bi = substr($employee->bi,0,1);
                            } else {
                                $model->new_bi = $level_gaji;
                            }
                            //$model->new_bi = $new_bi;
                            $model->new_department = $position->department;
                            $model->new_directorate = $position->directorate;
                            $model->new_division = $position->division;
                            $model->new_egroup = $position->egrp;
                            $model->new_kode_kota = $kode_kota_baru;
                            $model->new_kota = $kota_baru;
                            $model->new_organization = $position->organization;
                            $model->new_section = $position->section;
                            $model->new_title = $position_baru;
                            $model->new_region = $position->region;
                            $model->code_template = $code;
                            $model->structural = $position->structural;
                            $model->functional = $position->functional;
                            $model->effective_date = date("Y-m-d",strtotime($ba_date));
                            $model->tipe = $tipe;
                            $model->nota_dinas = $nota_dinas;
                            $model->periode = $periode;
                            $model->nama_penyakit = $nama_penyakit;
                            $model->nominal_insentif = (empty($nominal_insentif)) ? $nominal_insentif : str_replace(",","",$nominal_insentif);
                            $model->keputusan_direksi_1 = $kd_1;
                            $model->keputusan_direksi_2 = $kd_2;
                            $model->keputusan_direksi_3 = $kd_3;
                            $model->keterangan_ba_1 = $ba_1;
                            $ba_2 = str_replace("Broadband Salary", "<i>Broadband Salary</i>", $ba_2);
                            $model->keterangan_ba_2 = $ba_2;
                            $model->keterangan_ba_3 = $ba_3;
                            $model->cltp_reason = $cltp_reason;
                            $model->start_date_sick = (empty($start_sick)) ? $start_sick : date("Y-m-d",strtotime($start_sick));
                            $model->end_date_sick = (empty($end_sick)) ? $end_sick : date("Y-m-d",strtotime($end_sick));
                            $model->phk_date = (empty($phk_date)) ? $phk_date : date("Y-m-d",strtotime($phk_date));
                            $model->tanggal_td_pernyataan = (empty($statement_date)) ? $statement_date : date("Y-m-d",strtotime($statement_date));
                            $model->last_payroll = (empty($last_payroll)) ? $last_payroll : date("Y-m-d",strtotime($last_payroll));
                            $model->resign_date = (empty($resign_date)) ? $resign_date : date("Y-m-d",strtotime($resign_date));
                            $model->scholarship_program = $scholarship_program;
                            $model->scholarship_university = $scholarship_university;
                            $model->scholarship_level = $scholarship_level;
                            $model->band = $employee->band;
                            //if($check_position->bp != "" && intval(substr($check_position->bp,0,1)) <= 0) {
                            
                            
                            if($level_posisi !== NULL) {
                                $model->level_band = $level_posisi;
                            } else {
                                $model->level_band = $check_position->band;
                            }
                            //} 
                            /* // kalo ngga ketemu di errorin aja, ini malah bikin ngga valid.
                            else {
                                $model->level_band = intval(substr($employee->bp,0,1));
                            }*/
                            if($level_gaji == "" || empty($level_gaji)) {
                                $model->level_gaji = substr($employee->bi,0,1);
                            } else {
                                $model->level_gaji = $level_gaji;
                            }
                            $model->flag_kisel = $flag_kisel;
                            $model->gaji_dasar_nss = intval($gaji_dasar_nss);
                            $model->tbh_nss = intval($tbh_nss);
                            $model->tunjangan_rekomposisi_nss = intval($tunjangan_rekomposisi_nss);
                            if($model->level_band > 1 && !empty($model->level_band)) {
                                if($tunjab_nss < 0 || empty($tunjab_nss) ||  is_null($tunjab_nss) == true) {
                                    $data_tunjab         = Model::getTunjabNss($model->level_band);
                                    $model->tunjab_nss   = $data_tunjab['tunjab_nss'];      
                                } else {
                                    $model->tunjab_nss = $tunjab_nss;
                                }
                            }
                            $model->kr_organisasi_bss = $kr_bss;
                            $datapersen = EskTunjabNss::findOne(['level' => $model->level_gaji]);
                            $model->persen_biaya_hidup_bss = $datapersen->persen_biaya_hidup;
                            $model->persen_rekom_bss = $datapersen->persen_rekom;
                            
                            // perubahan approval mpp dan sakit berkepanjangan - 12092024 -ejes
                            
                            if (strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) {
                                $model->flag_ba_manager     = 1;
                                $model->nik_approved_ba     = '86149'; // approval pertama ke mas arnold
                           
                            }else{  
                                
                                if($model->level_band <= 3) {
                                    $model->flag_ba_manager         = 1;
                                    //$model->nik_approved_ba   = $this->getHeadCreator();
                                    //find so
                                    $so = EskSo::find()->where(['nik' => Yii::$app->user->identity->employee->nik])->andWhere(['directorate' => $model->old_directorate])->one();
                                    if(!empty($so)) {
                                        $model->nik_approved_ba = $so->so_nik;
                                    } else {
                                        $model->nik_approved_ba = Yii::$app->user->identity->employee->nik_atasan;
                                    }
                                
                                // >> } elseif($model->level_band >=4) {
                                } elseif( $model->level_band >=4 ) {
                                    $model->flag_ba_manager     = 1;
                                    $model->nik_approved_ba     = '86149'; // approval pertama ke mas arnold
                                } 
                            }
                            //var_dump($this->getHeadCreator());exit;
                            $model->created_by = Yii::$app->user->identity->nik;
                            // add by faqih
                            $model->nik_new_atasan = $nik_new_atasan;
                            $model->tunjangan_hot_skill = $tunjangan_hot_skill;
                            $model->tunjangan_aktualisasi = $tunjangan_aktualisasi; // sprint 3
                            //$model->tunjangan_aktualisasi = $tunjangan_aktualisasi; // takeout by tirta 10-09-2024

                            $model->grade = $grade;
                            $model->dpe_length = $dpe_length;
                            $model->dpe_unit = $dpe_unit;
                            // end
                            // add by faqih sprint 3
                            $model->notif_stat_date = $notif_stat_date;
                            $model->new_nik = $new_nik;
                            $model->leaving_reason = $leaving_reason;
                            // end
                    
                            //var_dump($model->level_band, $model->tunjab_nss);exit;
                            if($model->save()){
                                if($temp == null){
                                    $model_2 = EskBeritaAcaraDetailOtherTemp::findOne($model->id);
                                }else{
                                    $model_2 = EskBeritaAcaraDetailOther::findOne($model->id);
                                
                                    if($model_2->level_band >= 4) {
                                        $model_2->flag_esk = 6;
                                    }
                                }

                                //check eSK
                                $check_esk_data = $this->checkGenerateEsk($model->id,$temp);
                                if($check_esk_data['result'] == "success"){
                                    //generate eSK 
                                    $generate_data = $this->generateEsk($model->id,$temp);
                                    if($generate_data['result'] == "success"){
                                        $countSuccess = $countSuccess + 1;

                                        //update strukctural data
                                        if($model_2->positionNew->structural == "Y"){
                                            $strukctural_data = $data_ba->positionNew->structural;
                                            $functional_data = null;
                                        }elseif($model_2->positionNew->functional == "Y"){
                                            $strukctural_data = null;
                                            $functional_data = $data_ba->positionNew->functional;
                                        }else{
                                            if($employee->structural == "Y"){
                                                $strukctural_data = $data_ba->employee->structural;
                                                $functional_data = null;
                                            }elseif($employee->functional == "Y"){
                                                $strukctural_data = null;
                                                $functional_data = $data_ba->employee->functional;
                                            }else{
                                                $strukctural_data = null;
                                                $functional_data = null;
                                            }
                                        }
                                        $model_2->structural = $strukctural_data;
                                        $model_2->functional = $functional_data;
                                        $model_2->save();
                                    }else{
                                        $countFailed = $countFailed + 1;
                                        array_push($failed_array,"data BA ".$generate_data['remark']);

                                        //delete data
                                        $model_2->delete();
                                    }
                                }else{
                                    $countFailed = $countFailed + 1;
                                    array_push($failed_array,"data BA ".$check_esk_data['remark']);

                                    //delete data
                                    $model_2->delete();
                                }  
                            }else{
                                //set failed count
                                $countFailed = $countFailed + 1;
    
                                //logging data
                                $error = implode(",",$model->getErrorSummary(true));
                                array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed because ".$error);
                            }
                        }else{
                            $data_ba_exist->person_id = $employee->person_id;
                            $data_ba_exist->nik = $nik;
                            $data_ba_exist->nama = $employee->nama;
                            $data_ba_exist->is_senior_staff = $is_senior_staff;
                            $data_ba_exist->old_position_id = $employee->position_id;
                            $data_ba_exist->new_position_id = $position_id_baru;
                            $data_ba_exist->old_title = $position_lama;
                            $data_ba_exist->old_area = $employee->area;
                            $data_ba_exist->old_bgroup = $employee->bgroup;
                            $data_ba_exist->old_bi = $employee->bi;
                            $data_ba_exist->old_bp = $employee->bp;
                            $data_ba_exist->old_department = $employee->department;
                            $data_ba_exist->old_directorate = $employee->directorate;
                            $data_ba_exist->old_division = $employee->division;
                            $data_ba_exist->old_egroup = $employee->egroup;
                            $data_ba_exist->old_kode_kota = $employee->kode_kota;
                            $data_ba_exist->old_kota = $employee->kota;
                            $data_ba_exist->old_organization = $employee->organization;
                            $data_ba_exist->old_section = $employee->section;
                            $data_ba_exist->old_region = $employee->admins;
                            $data_ba_exist->new_area = $position->area;
                            $data_ba_exist->new_bgroup = $position->grp;
                            $data_ba_exist->new_bp = (!empty($new_bp)) ? $new_bp : $position->bp;
                            if($level_gaji == "" || empty($level_gaji)) {
                                $data_ba_exist->new_bi = substr($employee->bi,0,1);
                            } else {
                                $data_ba_exist->new_bi = $level_gaji;
                            }
                            //$data_ba_exist->new_bi = $new_bi;
                            $data_ba_exist->new_department = $position->department;
                            $data_ba_exist->new_directorate = $position->directorate;
                            $data_ba_exist->new_division = $position->division;
                            $data_ba_exist->new_egroup = $position->egrp;
                            $data_ba_exist->new_kode_kota = $kode_kota_baru;
                            $data_ba_exist->new_kota = $kota_baru;
                            $data_ba_exist->new_organization = $position->organization;
                            $data_ba_exist->new_section = $position->section;
                            $data_ba_exist->new_title = $position_baru;
                            $data_ba_exist->new_region = $position->region;
                            $data_ba_exist->code_template = $code;
                            $data_ba_exist->structural = $position->structural;
                            $data_ba_exist->functional = $position->functional;
                            $data_ba_exist->effective_date = date("Y-m-d",strtotime($ba_date));
                            $data_ba_exist->tipe = $tipe;
                            $data_ba_exist->nota_dinas = $nota_dinas;
                            $data_ba_exist->periode = $periode;
                            $data_ba_exist->nama_penyakit = $nama_penyakit;
                            $data_ba_exist->nominal_insentif = (empty($nominal_insentif)) ? $nominal_insentif : str_replace(",","",$nominal_insentif);
                            $data_ba_exist->keputusan_direksi_1 = $kd_1;
                            $data_ba_exist->keputusan_direksi_2 = $kd_2;
                            $data_ba_exist->keputusan_direksi_3 = $kd_3;
                            $data_ba_exist->keterangan_ba_1 = $ba_1;
                            $ba_2 = str_replace("Broadband Salary", "<i>Broadband Salary</i>", $ba_2);
                            $data_ba_exist->keterangan_ba_2 = $ba_2;
                            $data_ba_exist->keterangan_ba_3 = $ba_3;
                            $data_ba_exist->cltp_reason = $cltp_reason;
                            $data_ba_exist->start_date_sick = (empty($start_sick)) ? $start_sick : date("Y-m-d",strtotime($start_sick));
                            $data_ba_exist->end_date_sick = (empty($end_sick)) ? $end_sick : date("Y-m-d",strtotime($end_sick));
                            $data_ba_exist->phk_date = (empty($phk_date)) ? $phk_date : date("Y-m-d",strtotime($phk_date));
                            $data_ba_exist->tanggal_td_pernyataan = (empty($statement_date)) ? $statement_date : date("Y-m-d",strtotime($statement_date));
                            $data_ba_exist->last_payroll = (empty($last_payroll)) ? $last_payroll : date("Y-m-d",strtotime($last_payroll));
                            $data_ba_exist->resign_date = (empty($resign_date)) ? $resign_date : date("Y-m-d",strtotime($resign_date));
                            $data_ba_exist->scholarship_program = $scholarship_program;
                            $data_ba_exist->scholarship_university = $scholarship_university;
                            $data_ba_exist->scholarship_level = $scholarship_level;
                            $data_ba_exist->flag_kisel = $flag_kisel;
                            $data_ba_exist->band = $employee->band;
                            if($level_posisi !== NULL) {
                                $data_ba_exist->level_band = $level_posisi;
                            } else {
                                $data_ba_exist->level_band = $check_position->band;
                            }
                            if($level_gaji == "" || empty($level_gaji)) {
                                $data_ba_exist->level_gaji = substr($employee->bi,0,1);
                            } else {
                                $data_ba_exist->level_gaji = $level_gaji;
                            }
                            $data_ba_exist->gaji_dasar_nss = $gaji_dasar_nss;
                            $data_ba_exist->tbh_nss = $tbh_nss;
                            $data_ba_exist->tunjangan_rekomposisi_nss = $tunjangan_rekomposisi_nss;
                            $data_ba_exist->tunjab_nss = $tunjab_nss;
                            if($employee->band > 1 && !empty($employee->band)) {
                                if($tunjab_nss < 0 || empty($tunjab_nss) ||  is_null($tunjab_nss) == true) {
                                    $data_tunjab         = Model::getTunjabNss($data_ba_exist->level_band);
                                    $data_ba_exist->tunjab_nss   = $data_tunjab['tunjab_nss'];      
                                } else {
                                    $data_ba_exist->tunjab_nss = $tunjab_nss;
                                }
                            }
                            $data_ba_exist->kr_organisasi_bss = $kr_bss;
                            $datapersen = EskTunjabNss::findOne(['level' => $data_ba_exist->level_gaji]);
                            $data_ba_exist->persen_biaya_hidup_bss = $datapersen->persen_biaya_hidup;
                            $data_ba_exist->persen_rekom_bss = $datapersen->persen_rekom;

                            // add by faqih
                            $data_ba_exist->nik_new_atasan = $nik_new_atasan;
                            $data_ba_exist->tunjangan_hot_skill = $tunjangan_hot_skill;
                            $data_ba_exist->tunjangan_aktualisasi = $tunjangan_aktualisasi; // sprint 3
                            //$data_ba_exist->tunjangan_aktualisasi = $tunjangan_aktualisasi; // takeout by tirta 10-09-2024

                            $data_ba_exist->grade = $grade;
                            $data_ba_exist->dpe_length = $dpe_length;
                            $data_ba_exist->dpe_unit = $dpe_unit;
                            // end
                            // add by faqih sprint 3
                            $data_ba_exist->notif_stat_date = $notif_stat_date;
                            $data_ba_exist->new_nik = $new_nik;
                            $data_ba_exist->leaving_reason = $leaving_reason;
                            // end
                            
                            if($data_ba_exist->save()){
                                //check eSK
                                $check_esk_data = $this->checkGenerateEsk($data_ba_exist->id,$temp);
                                if($check_esk_data['result'] == "success"){
                                    //regenerate data esk
                                    $generate_data = $this->regenerateEsk($data_ba_exist->id,$temp);
                                    if($generate_data['result'] == "success"){
                                        $countSuccess = $countSuccess + 1;

                                        //update strukctural data
                                        if($rollback_update->positionNew->structural == "Y"){
                                            $strukctural_data = $data_ba->positionNew->structural;
                                            $functional_data = null;
                                        }elseif($rollback_update->positionNew->functional == "Y"){
                                            $strukctural_data = null;
                                            $functional_data = $data_ba->positionNew->functional;
                                        }else{
                                            if($employee->structural == "Y"){
                                                $strukctural_data = $data_ba->employee->structural;
                                                $functional_data = null;
                                            }elseif($employee->functional == "Y"){
                                                $strukctural_data = null;
                                                $functional_data = $data_ba->employee->functional;
                                            }else{
                                                $strukctural_data = null;
                                                $functional_data = null;
                                            }
                                        }
                                        $rollback_update->structural = $strukctural_data;
                                        $rollback_update->functional = $functional_data;
                                        $rollback_update->save();
                                    }else{
                                        $countFailed = $countFailed + 1;
                                        array_push($failed_array,"data BA ".$generate_data['remark']);

                                        //balikan kembali datanya
                                        $rollback_update->attributes = $data_ba_old->attributes;
                                        $rollback_update->save();
                                    }
                                }else{
                                    $countFailed = $countFailed + 1;
                                    array_push($failed_array,"data BA ".$check_esk_data['remark']);

                                    //balikan kembali datanya
                                    $rollback_update->attributes = $data_ba_old->attributes;
                                    $rollback_update->save();
                                } 
                            }else{
                                //set failed count
                                $countFailed = $countFailed + 1;
    
                                //logging data
                                $error = implode(",",$data_ba_exist->getErrorSummary(true));
                                array_push($failed_array,"data Employee for row ".$i." (".$item['TIPE_BA']."/".$item['NIK'].") failed update because ".$error);
                            }
                        }

                        //count iteration
                        $i++;
                        $countAll = $countAll + 1;
                    }else{
                        if(
                            isset($item['TIPE_BA']) || isset($item['CODE_TEMPLATE']) || 
                            isset($item['NIK']) || isset($item['EFFECTIVE_DATE']) ||
                            isset($item['NEW_POSITION']) || isset($item['NEW_ORGANIZATION']) ||
                            isset($item['NEW_CITY']) || isset($item['NEW_BP'])
                        ){
                            //set failed count
                            $countFailed = $countFailed + 1;
                                    
                            //logging data
                            array_push($failed_array,"data Employee for row ".$i." failed because some mandatory field is empty!");
                        }
                    }
                }

                if(!empty($failed_array)){
                    $failed_data = "that is ".implode(", ",array_unique($failed_array));
                }else{
                    $failed_data = "";
                }

                //set flash message
                Yii::$app->session->setFlash('info', 'Successfully import ' . $countAll . ' data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '); 
                //Yii::$app->session->setFlash('info', 'Successfully import ' . $countAll . ' data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Import data of Employee");

                //export error to file text
                if(!empty($failed_data)){
                    $data_error = implode("\n",array_unique($failed_array));
                }    
            }else{
                Yii::$app->session->setFlash('error', "Not found file, please upload again or call administrator!");
            }

            if($temp == null){
                $file_open = 'create_ba';
                $model_ba = new EskBeritaAcaraOther();
            }else{
                $file_open = 'update_ba';
                $model_ba = EskBeritaAcaraOther::findOne($batch_number);
            }

            return $this->render($file_open, [
                'model' => $model_ba,
                'batch_number' => $batch_number,
                'data_error' => $data_error,
                'flag_reject' => $flag_reject
            ]);

        }

        return $this->renderAjax('upload');
    }
    
    public function actionShowError($data){
        Model::exportError($data);
    }

    public function actionGetTemplate(){
        $nama = Helper::TEMPLATE_IMPORT_BA;
        $file = Yii::getAlias('@esk/web/' . $nama);
        return Yii::$app->response->sendFile($file, NULL, ['inline' => TRUE]);
      /* 
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $spreadsheet->getCalculationEngine()->suppressFormulaErrors=true;
        // $worksheet2 = $spreadsheet->createSheet()->setTitle('Worksheet2');

        $spreadsheet->getProperties()->setCreator('Faqih Fathurrahman')
            ->setLastModifiedBy('Faqih Fathurrahman')
            ->setTitle('Office 2007 XLSX Test Document')
            ->setSubject('Office 2007 XLSX Test Document')
            ->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')
            ->setKeywords('office 2007 openxml php')
            ->setCategory('Template For BA Other');
              

        $conn = Yii::$app->dbOra;
        $dataOra = $conn->createCommand('SELECT * FROM TSEL_HR_LEAVE_REASON_V')
            ->queryAll();


        // $fk = array();
        $no = 2;
        // $worksheet->setActiveSheetIndex(1)->getCell('B'. 1)->setValue("CODE");
        // $worksheet->setActiveSheetIndex(1)getCell('C'. 1)->setValue("MEANING");

        // $worksheet2->getCell('A2')->setValue('Hours');
        // $worksheet2->getCell('A3')->setValue('Days');
        // $worksheet2->getCell('A4')->setValue('Weeks');
        // $worksheet2->getCell('A5')->setValue('Months');
        // $worksheet2->getCell('A6')->setValue('Years');

        foreach($dataOra as $rows)
        {
            // array_push($fk, $rows['MEANING']);
            $row = $no;
            $spreadsheet->setActiveSheetIndex(1)->getCell('B'. $row)->setValue($rows['CODE']);
            $spreadsheet->setActiveSheetIndex(1)->getCell('C'. $row)->setValue($rows['MEANING']);

            // $worksheet2->getCell('B'. $row)->setValue($rows['CODE']);
            // $worksheet2->getCell('C'. $row)->setValue($rows['MEANING']);
            
            $no++;
        }
/*
        $validation1 = ''.$worksheet2->getTitle().'!$C$1:$C$'.$no.''; //'=Sheet2!$C$1:$C$46' // 'OFFSET(Sheet2!$A$1;1;2;COUNTA(Sheet2!$C:$';
        $validation2 = ''.$worksheet2->getTitle().'!$A$1:$A$5';
        for($i = 2; $i < 5; $i++){
            $objValidation = $spreadsheet->setActiveSheetIndex(0)->getCell('AJ2'.$i)->getDataValidation();
            $objValidation->setType(\PHPExcel_Cell_DataValidation::TYPE_LIST);
            $objValidation->setErrorStyle(\PHPExcel_Cell_DataValidation::STYLE_INFORMATION);
            $objValidation->setAllowBlank(false);
            $objValidation->setShowInputMessage(true);
            $objValidation->setShowDropDown(true);
            $objValidation->setPromptTitle('Pick Storage Condition For Terminate');
            $objValidation->setPrompt('Please pick a value from the drop-down list.');
            $objValidation->setErrorTitle('Input error');
            $objValidation->setError('Value is not in list');
            $objValidation->setFormula1($validation1);

            $DPE = ['Hours', 'Days', 'Weeks', 'Months', 'Years'];

            $objValidation1 = $spreadsheet->setActiveSheetIndex(0)->getCell('K2'.$i)->getDataValidation();
            $objValidation1->setType(\PHPExcel_Cell_DataValidation::TYPE_LIST);
            $objValidation1->setErrorStyle(\PHPExcel_Cell_DataValidation::STYLE_INFORMATION);
            $objValidation1->setAllowBlank(false);
            $objValidation1->setShowInputMessage(true);
            $objValidation1->setShowDropDown(true);
            $objValidation1->setPromptTitle('Pick Storage Condition');
            $objValidation1->setPrompt('Please pick a value from the drop-down list.');
            $objValidation1->setErrorTitle('Input error');
            $objValidation1->setError('Value is not in list');
            // $objValidation1->setFormula1('"'.implode(',', $DPE).'"');
            $objValidation1->setFormula1($validation2);
            
        }

        
*/
        /*
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="template_import_ba_other.xlsx"');
        header('Cache-Control: max-age=0');
        // Yii::$app->session->setFlash('success', 'berhasil download!');
        $writer->save('php://output');      
        exit;
        */
        
    }

    /**
     * Updates an existing BeritaAcara model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionUpdateDetail($id,$temp = null)
    {
        if(!empty($temp)){
            $model = EskBeritaAcaraDetailOther::findOne($id);
            $nama_file = '_update_ba';
            $flag_temp = false;
            $post_name = 'EskBeritaAcaraDetailOther';
        }else{
            $model = EskBeritaAcaraDetailOtherTemp::findOne($id);
            $nama_file = '_update';
            $flag_temp = true;
            $post_name = 'EskBeritaAcaraDetailOtherTemp';
        }
        $old_data = $model;
        $new_position_id_old = $model->new_position_id;

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();

            //explode data old kota dan new kota
            $data_old_kota = explode(":",$request[$post_name]['old_kota']);
            $data_new_kota = explode(":",$request[$post_name]['new_kota']);

            //get data generate
            $old_bp = $request[$post_name]['old_bp'];
            $new_bp = $request[$post_name]['new_bp'];
            $old_bi = $request[$post_name]['old_bi'];
            $new_bi = $request[$post_name]['new_bi'];
            $old_kode_kota = $data_old_kota[0];
            $new_kode_kota = $data_new_kota[0];
            $old_kota = $data_old_kota[1];
            $new_kota = $data_new_kota[1];
            $old_area = $request[$post_name]['old_area'];
            $new_area = $request[$post_name]['new_area'];
            $new_position_id = $request[$post_name]['new_position_id'];
            if(strpos($new_position_id,"Senior Staff") !== false){
                $position_id = (is_numeric($request['position_id_org'])) ? $request['position_id_org'] : $new_position_id_old;
                $position_data = Position::find()->where(['id' => $position_id])->one();
                $is_senior_staff = 1;
                $new_title_data = Model::replaceSeniorStaff($position_data->organization);
                $change_title = false;
            }else{
                $position_id = $request[$post_name]['new_position_id'];
                $position_data = Position::find()->where(['id' => $position_id])->one();
                $is_senior_staff = 0;
                $new_title_data = $position_data->nama;
                $change_title = true;
            }

            //optional content
            $nota_dinas = $request[$post_name]['nota_dinas'];
            $periode = $request[$post_name]['periode'];
            $nama_penyakit = $request[$post_name]['nama_penyakit'];
            $nominal_insentif = str_replace(",","",$request[$post_name]['nominal_insentif']);
            $kd_1 = $request[$post_name]['keputusan_direksi_1'];
            $ba_1 = $request[$post_name]['keterangan_ba_1'];
            $kd_2 = $request[$post_name]['keputusan_direksi_2'];
            $ba_2 = $request[$post_name]['keterangan_ba_2'];
            $kd_3 = $request[$post_name]['keputusan_direksi_3'];
            $ba_3 = $request[$post_name]['keterangan_ba_3'];
            $scholar_program = $request[$post_name]['scholarship_program'];
            $scholar_university = $request[$post_name]['scholarship_university'];
            $scholar_level = $request[$post_name]['scholarship_level'];
            $cltp_reason = $request[$post_name]['cltp_reason'];
            $start_sick = empty($request[$post_name]['start_date_sick']) ? "" : date("Y-m-d",strtotime($request[$post_name]['start_date_sick']));
            $end_sick = empty($request[$post_name]['end_date_sick']) ? "" : date("Y-m-d",strtotime($request[$post_name]['end_date_sick']));
            $phk_date = empty($request[$post_name]['phk_date']) ? "" : date("Y-m-d",strtotime($request[$post_name]['phk_date']));
            $statement_date = empty($request[$post_name]['tanggal_td_pernyataan']) ? "" : date("Y-m-d",strtotime($request[$post_name]['tanggal_td_pernyataan']));
            $last_payroll_date = empty($request[$post_name]['last_payroll']) ? "" : date("Y-m-d",strtotime($request[$post_name]['last_payroll']));
            $resign_date = empty($request[$post_name]['resign_date']) ? "" : date("Y-m-d",strtotime($request[$post_name]['resign_date']));
            $flag_kisel = $request[$post_name]['flag_kisel'];
            // add by faqih
            $tunjangan_hot_skill = $request[$post_name]['tunjangan_hot_skill'];
            $tunjangan_aktualisasi = $request[$post_name]['tunjangan_aktualisasi']; // sprint 3
            $nik_new_atasan = $request[$post_name]['nik_new_atasan'];
            $dpe_length = $request[$post_name]['dpe_length'];
            $dpe_unit = $request[$post_name]['dpe_unit'];
            $grade = $request[$post_name]['grade'];
            // end

            // add by faqih sprint 3
            $notif_stat_date = $request[$post_name]['notif_stat_date'];
            $new_nik = $request[$post_name]['new_nik'];
            $leaving_reason = $request[$post_name]['leaving_reason'];
            // end

            //validasi struktural dan functional 
            if($model->positionNew->structural == "Y"){
                $strukctural_data = $model->positionNew->structural;
                $functional_data = null;
            }elseif($model->positionNew->functional == "Y"){
                $strukctural_data = null;
                $functional_data = $model->positionNew->functional;
            }else{
                if($model->employee->structural == "Y"){
                    $strukctural_data = $model->employee->structural;
                    $functional_data = null;
                }elseif($model->employee->functional == "Y"){
                    $strukctural_data = null;
                    $functional_data = $model->employee->functional;
                }else{
                    $strukctural_data = null;
                    $functional_data = null;
                }
            }

            $model->tipe = $request[$post_name]['tipe'];
            $model->is_senior_staff = $is_senior_staff;
            $model->new_position_id = $position_id;
            $model->new_title = $new_title_data;
            $model->old_kode_kota = $old_kode_kota;
            $model->new_kode_kota = $new_kode_kota;
            $model->old_kota = $old_kota;
            $model->new_kota = $new_kota;
            $model->structural = $strukctural_data;
            $model->functional = $functional_data;
            $model->nota_dinas = $nota_dinas;
            $model->periode = $periode;
            $model->nama_penyakit =  $nama_penyakit;
            $model->nominal_insentif = $nominal_insentif;
            $model->keterangan_ba_1 = $ba_1;
            $model->keterangan_ba_2 = $ba_2;
            $model->keterangan_ba_3 = $ba_3;
            $model->keputusan_direksi_1 = $kd_1;
            $model->keputusan_direksi_2 = $kd_2;
            $model->keputusan_direksi_3 = $kd_3;
            $model->cltp_reason = $cltp_reason;
            $model->scholarship_program = $scholar_program;
            $model->scholarship_university = $scholar_university;
            $model->scholarship_level = $scholar_level;
            $model->start_date_sick = $start_sick;
            $model->end_date_sick = $end_sick;
            $model->phk_date = $phk_date;
            $model->tanggal_td_pernyataan = $statement_date;
            $model->last_payroll = $last_payroll_date;
            $model->resign_date = $resign_date;
            $model->flag_kisel = $flag_kisel;
            // add by faqih
            $model->tunjangan_hot_skill = $tunjangan_hot_skill;
            $model->tunjangan_aktualisasi = $tunjangan_aktualisasi; // sprint 3
            $model->nik_new_atasan = $nik_new_atasan;
            $model->dpe_length = $dpe_length;
            $model->dpe_unit = $dpe_unit;
            $model->grade = $grade;
            // end

            // add by faqih sprint 3
            $model->notif_stat_date = $notif_stat_date;
            $model->new_nik = $new_nik;
            $model->leaving_reason = $leaving_reason;
            // 

            if($model->save()){
                //update lagi datanya untuk title
                if($change_title){
                    if(!$flag_temp){
                        $model2 = EskBeritaAcaraDetailOther::findOne($model->id);
                    }else{
                        $model2 = EskBeritaAcaraDetailOtherTemp::findOne($model->id);
                    }
                    $model2->old_title = $model->positionOld->nama;
                    $model2->new_title = $model->positionNew->nama;
                    $model2->save();
                }

                //check eSK
                $check_esk_data = $this->checkGenerateEsk($model->id,$temp);
                if($check_esk_data['result'] == "success"){
                    //regenerate data esk
                    $generate_data = $this->regenerateEsk($model->id,$temp);
                    if($generate_data['result'] == "success"){
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Update BA Lists with ID ".$model->id);
                        Yii::$app->session->setFlash('success', "BA data successfully updated!");
                    }else{
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Failed update data BA ".$generate_data['remark']);
                        Yii::$app->session->setFlash('error', "Failed update data BA ".$generate_data['remark']);

                        //balikan kembali datanya
                        $model->attributes = $old_data->attributes;
                        $model->save();
                    }
                }else{
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Failed update data BA ".$check_esk_data['remark']);
                    Yii::$app->session->setFlash('error', "Failed update data BA ".$check_esk_data['remark']);

                    //balikan kembali datanya
                    $model->attributes = $old_data->attributes;
                    $model->save();
                } 
            }else{
                //logging data
                $error = implode(",",$model->getErrorSummary(true));
                Model::saveLog(Yii::$app->user->identity->username, "Failed update BA data for ID ".$model->id." because ".$error);
                Yii::$app->session->setFlash('error', "Failed update, because ".$error);
            }

            //return to create BA page
            $model_master = new EskBeritaAcaraOther();
            if(!$flag_temp){
                $flag_reject_data = ($model->flag_esk == 2) ? 1 : 0;
                return $this->redirect(['update-berita-acara','id' => $model->id_master, 'flag_reject' => $flag_reject_data]);
            }else{
                return $this->redirect(['create-berita-acara','batch_number' => $model->batch_number]);
            }
        }

        //check if kode kota empty
        $old_kode_kota = City::find()->where(['code' => $model->old_kode_kota])->one();
        $new_kode_kota = City::find()->where(['code' => $model->new_kode_kota])->one();

        $model->old_kota = strtoupper($model->old_kota);
        $model->old_kode_kota = !empty($model->old_kode_kota) ? $model->old_kode_kota : (empty($old_kode_kota) ? '' : $old_kode_kota->code);
        $model->new_kode_kota = !empty($model->new_kode_kota) ? $model->new_kode_kota : (empty($new_kode_kota) ? '' : $new_kode_kota->code);
        $model->new_kota = strtoupper($model->new_kota);

        $data_emp = Employee::find()->where(['nik' => $model->nik])->one();
        $position_id_org = $model->positionNew->organization." (Type of ".$model->positionNew->organization_type.")";
        if($model->is_senior_staff  == 1){
            $model->new_position_id = "Senior Staff";
        }

        return $this->renderAjax($nama_file, [
            'model' => $model,
            'emp_data' => $data_emp,
            'position_id_org' => $position_id_org,
            'temp' => $temp
        ]);
    }

    public function actionUpdateBeritaAcara($id,$flag_reject){
        $model = EskBeritaAcaraOther::findOne($id);

        if (Yii::$app->request->post()) {
            //get data master
            $request = Yii::$app->request->post();
            $directorate = $request['directorate'];
            $tipe = $request['tipe'];
            $ba_date = date("Y-m-d",strtotime($request['ba_date']));
            $area = (empty(Yii::$app->user->identity->employee)) ? "HEAD OFFICE" : Yii::$app->user->identity->employee->area;
            $status = $request['status'];

            //set sikom_berita_acara
            $model->area = $area;
            $model->directorate = $directorate;
            $model->ba_date = $ba_date;
            $model->tipe = $tipe;
            $model->status = $status;

            //get data ba
            if($flag_reject == 1){
                $ba_other_detail = EskBeritaAcaraDetailOther::find()->where(['id_master' => $model->id,'flag_esk' => 2])->all();
            }else{
                $ba_other_detail = EskBeritaAcaraDetailOther::find()->where(['id_master' => $model->id])->all();
            }

            //save data master
            $transaction = \Yii::$app->db->beginTransaction();
            try {
                if ($model->save()) {      
                    $transaction->commit();              
                    //save data ke db comcar.berita_acara dan comcar.berita_acara_detail
                    if($status == "1" || $status == 1){
                        try{
                            //save detail data ke table berita_acara_detail
                            $flag_update = false;
                            $detail_mail_ba = array();
                            foreach($ba_other_detail as $detail){
                                if(!empty($detail->keterangan_ba_1) && strpos($detail->keterangan_ba_1,"{nomor_pembuatan_ba}") !== false){
                                    $replace_data = str_replace("{nomor_pembuatan_ba}",$model->no,$detail->keterangan_ba_1);
                                    $detail->keterangan_ba_1 = str_replace("{tanggal_pembuatan_ba}",Model::TanggalIndo(date('Y-m-d')),$replace_data);
                                    $flag_update = true;
                                }

                                if($flag_reject == 1 || $flag_update){
                                    //update data
                                    $detail->flag_esk = 0;
                                    $detail->save();
                                    
                                    //update keterangan berita acara
                                    $esk_lists_temp = EskListsTemp::find()->where(['id_ba_detail' => $detail->id])->one();
                                    $esk_lists_temp->keterangan_ba_1 = $detail->keterangan_ba_1;

                                    if(!$esk_lists_temp->save()){
                                        $transaction->rollBack();
                                        Yii::$app->session->setFlash('error', "Berita Acara data was not created.");
                                    }else{
                                        //regenerate eSK
                                        $this->regenerateEsk($detail->id,"1");
                                    }
                                }

                                //set detail data for email to approval
                                array_push($detail_mail_ba, '
                                    <tr>
                                        <td>'.$detail->nik.'</td>
                                        <td>'.$detail->nama.'</td>
                                        <td>'.$detail->old_title.'</td>
                                        <td>'.$detail->new_title.'</td>
                                        <td>'.$detail->tipe.'</td>
                                    </tr>
                                ');
                            }
                        }catch(\yii\db\Exception $e){
                            $transaction->rollBack();
                            Yii::$app->session->setFlash('error', "Berita Acara data was not created because ".$e);
                            return $this->redirect(['index']);
                        }    

                        //send mail to atasan employee min. GM
                        $subject = "[eSK] Approval of Berita Acara Number ".$model->no."";
                        $to = $model->approvedBy->email;
                        $content = $this->renderPartial('../../mail/mail-approval-ba',['data_master' => $model, 'data_detail' => $detail_mail_ba, 'nama' => $model->approvedBy->nama],true);
                        Model::sendMailOne($to,$subject,$content);
                    }
                    
                    Yii::$app->session->setFlash('success', "Berita Acara data successfully created."); 

                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Create Berita Acara data with ID ".$model->id);
                }else{
                    $transaction->rollBack();
                    Yii::$app->session->setFlash('error', "Berita Acara data was not created.");
                }
            } catch (Exception $e) {
                // penyimpanan gagal, rollback database transaction
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', "Berita Acara data was not created because ".$e);
            }
            
            return $this->redirect(['index']);
        }
        
        return $this->render('update_ba', [
            'model' => $model,
            'flag_reject' => $flag_reject
        ]);
    }

    public function checkGenerateEsk($ba,$temp){
        //inisialisasi awal
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $model = new EskListsTemp();

        if($temp == null){
            $data_ba = EskBeritaAcaraDetailOtherTemp::find($ba)->where(['id' => $ba])->one();
        }else{
            $data_ba = EskBeritaAcaraDetailOther::find($ba)->where(['id' => $ba])->one();
        }

        if(!empty($data_ba->new_bi)){
            $bi = $data_ba->new_bi;
        }else{
            $bi = $data_ba->old_bi;
        }

        //get data mutasi, eva dan dpe 
        $dataMED = Model::getMutasiEvaDpe($data_ba->old_kota, $data_ba->new_kota, $data_ba->old_kode_kota, $data_ba->new_kode_kota, $data_ba->tipe, $data_ba->dpe);

        //explode data BP 
        $databp = explode(".",$data_ba->new_bp);                
        $dataoldbp = explode(".",$data_ba->old_bp);   

        //search tipe ba di template esk master
        if(is_null($databp[0]) || $databp[0] == "") {
            $dataemployee = Employee::findOne(['nik' => $data_ba->nik]);
            $databp = explode(".",$dataemployee->bp);
        }
        
        //var_dump($data_ba->tipe,$data_ba->code_template,$databp[0],$data_ba->old_area,$data_ba->new_area,$data_ba->old_directorate);exit;
        /** ejes 011024 ada merubah di model.php terkait template master*/
        $data_esk_master = Model::checkTemplateMaster($data_ba->tipe,$data_ba->code_template,$databp[0],$data_ba->old_area,$data_ba->new_area,$data_ba->old_directorate);
        //var_dump($data_esk_master);exit;
        if(!empty($data_esk_master)){
            //default value
            $flag_gaji = 1;
            $flag_uang_pisah = null;
            $flag_ganti_rumah = null;
            $flag_ganti_cuti = null;
            $flag_homebase = null;
            $flag_insentif = 1;
            $flag_ket_kerja = 1;

            //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
            $content_sk = Model::generateEsk($data_esk_master['id'],$data_ba->nik,$data_ba->flag_kisel,$data_ba->last_payroll,"",$flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja, "", 1);

            //check apakah content ada code periode atau nodin 
            if( (strpos($content_sk,"{periode}") !== false && empty($periode) && empty($data_ba->periode)) 
            || (strpos($content_sk,"{nota_dinas}") !== false && empty($nodin) && empty($data_ba->nota_dinas))
            || (strpos($content_sk,"{nama_penyakit}") !== false && empty($nama_penyakit) && empty($data_ba->nama_penyakit))
            || (strpos($content_sk,"{nominal_insentif}") !== false && empty($nominal_insentif) && empty($data_ba->nominal_insentif))
            || (strpos($content_sk,"{manual_content_1}") !== false && empty($manual_content_1))
            || (strpos($content_sk,"{manual_content_2}") !== false && empty($manual_content_2))
            || (strpos($content_sk,"{manual_content_3}") !== false && empty($manual_content_3))
            || (strpos($content_sk,"{keterangan_ba_1}") !== false && empty($keterangan_ba_1) && empty($data_ba->keterangan_ba_1))
            || (strpos($content_sk,"{keterangan_ba_2}") !== false && empty($keterangan_ba_2) && empty($data_ba->keterangan_ba_2)) 
            || (strpos($content_sk,"{keterangan_ba_3}") !== false && empty($keterangan_ba_3) && empty($data_ba->keterangan_ba_3)) 
            || (strpos($content_sk,"{keputusan_direksi_1}") !== false && empty($keputusan_direksi_1) && empty($data_ba->keputusan_direksi_1))
            || (strpos($content_sk,"{keputusan_direksi_2}") !== false && empty($keputusan_direksi_2) && empty($data_ba->keputusan_direksi_2)) 
            || (strpos($content_sk,"{keputusan_direksi_3}") !== false && empty($keputusan_direksi_3) && empty($data_ba->keputusan_direksi_3))
            || (strpos($content_sk,"{gaji_dasar_bss}") !== false && empty($gaji_dasar_nss) && empty($data_ba->gaji_dasar_nss))
            || (strpos($content_sk,"{tbh_bss}") !== false && empty($tbh_nss) && empty($data_ba->tbh_nss))
            || (strpos($content_sk,"{tunjangan_rekomposisi_bss}") !== false && empty($tunjangan_rekomposisi_nss) && empty($data_ba->tunjangan_rekomposisi_nss))
            || (strpos($content_sk,"{tunjangan_jabatan_bss}") !== false && empty($tunjab_nss) && empty($data_ba->tunjab_nss))
            || (strpos($content_sk,"{kr_organisasi_bss}") !== false && empty($kr_bss) && empty($data_ba->kr_organisasi_bss))
            || (strpos($content_sk,"{tunjangan_hot_skill}") !== false && empty($tunjangan_hot_skill) && empty($data_ba->tunjangan_hot_skill))
            || (strpos($content_sk,"{new_nik}") !== false && empty($new_nik) && empty($data_ba->new_nik))
            ){
                $flag_periode = strpos($content_sk,"{periode}") !== false ? '1' : '0';
                $flag_nodin = strpos($content_sk,"{nota_dinas}") !== false ? '1' : '0';
                $flag_nama_penyakit = strpos($content_sk,"{nama_penyakit}") !== false ? '1' : '0';
                $flag_nominal_insentif = strpos($content_sk,"{nominal_insentif}") !== false ? '1' : '0';
                $flag_content_1 = strpos($content_sk,"{manual_content_1}") !== false ? '1' : '0';
                $flag_content_2 = strpos($content_sk,"{manual_content_2}") !== false ? '1' : '0';
                $flag_content_3 = strpos($content_sk,"{manual_content_3}") !== false ? '1' : '0';
                $flag_ba_1 = strpos($content_sk,"{keterangan_ba_1}") !== false ? '1' : '0';
                $flag_ba_2 = strpos($content_sk,"{keterangan_ba_2}") !== false ? '1' : '0';
                $flag_ba_3 = strpos($content_sk,"{keterangan_ba_3}") !== false ? '1' : '0';
                $flag_kd_1 = strpos($content_sk,"{keputusan_direksi_1}") !== false ? '1' : '0';
                $flag_kd_2 = strpos($content_sk,"{keputusan_direksi_2}") !== false ? '1' : '0';
                $flag_kd_3 = strpos($content_sk,"{keputusan_direksi_3}") !== false ? '1' : '0'; 
                $flag_gaji_dasar_nss = strpos($content_sk,"{gaji_dasar_bss}") !== false ? '1' : '0'; 
                $flag_tbh_nss = strpos($content_sk,"{tbh_bss}") !== false ? '1' : '0'; 
                $flag_tunjangan_rekomposisi_nss = strpos($content_sk,"{tunjangan_rekomposisi_bss}") !== false ? '1' : '0'; 
                $flag_tunjab_nss = strpos($content_sk,"{tunjangan_jabatan_bss}") !== false ? '1' : '0'; 
                $flag_tunjangan_hot_skill = strpos($content_sk,"{tunjangan_hot_skill}") !== false ? '1' : '0';
                $flag_new_nik = strpos($content_sk,"{new_nik}") !== false ? '1' : '0';
                
                $data_return = array(
                    "result" => "failed",
                    "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because some addtional content (BA/KD/Periode/Nota Dinas/Diseases Name/Insentif Amount) is empty!"
                );
            }

            //get id esk master
            //$id_approval = EskApprovalMaster::find()->where(['band' => $databp[0], 'authority_area' => $data_esk_master['authority']])->andWhere('directorate like "%'.$data_ba->old_directorate.'%"')->one();
            $id_approval = EskApprovalMaster::find()->where(['band' => $databp[0]])->andWhere('authority_area like "%'.$data_esk_master['authority'].'%"')->andWhere('directorate like "%'.$data_ba->old_directorate.'%"')->one();

            //var_dump($databp[0], $data_esk_master['authority'], $data_ba->old_directorate);exit;
            if(!empty($id_approval)){
                $data_return = array(
                    "result" => "success",
                    "remark" => ""
                );
            }else{
                $data_return = array(
                    "result" => "failed",
                    "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because approval data that matches type of esk not found"
                );
            }
        }else{
            //var_dump($databp[0], $data_esk_master['authority'], $data_ba->old_directorate);exit;
            $data_return = array(
                "result" => "failed",
                "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." template not found, please check again (type, code, band, old area and new area) or decree by"
            );
        }

        return $data_return;
    }

    public function generateEsk($ba,$temp){
        //inisialisasi awal
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $model = new EskListsTemp();

        if($temp == null){
            $data_ba = EskBeritaAcaraDetailOtherTemp::find()->where(['id' => $ba])->one();
        }else{
            $data_ba = EskBeritaAcaraDetailOther::find()->where(['id' => $ba])->one();
        }

        if(!empty($data_ba->new_bi)){
            $bi = $data_ba->new_bi;
        }else{
            $bi = $data_ba->old_bi;
        }

        //get data mutasi, eva dan dpe 
        $dataMED = Model::getMutasiEvaDpe($data_ba->old_kota, $data_ba->new_kota, $data_ba->old_kode_kota, $data_ba->new_kode_kota, $data_ba->tipe, $data_ba->dpe);

        //explode data BP 
        $databp = explode(".",$data_ba->new_bp);                
        $dataoldbp = explode(".",$data_ba->old_bp);   
        
        if(is_null($databp[0]) == true || $databp[0] == "") {
            $dataemployee = Employee::findOne(['nik' => $data_ba->nik]);
            $databp = explode(".",$dataemployee->bp);
        }
        
        //search tipe ba di template esk master
        $data_esk_master = Model::checkTemplateMaster($data_ba->tipe,$data_ba->code_template,$databp[0],$data_ba->old_area,$data_ba->new_area,$data_ba->old_directorate);
        if(!empty($data_esk_master)){
            //default value
            $flag_gaji = 1;
            $flag_uang_pisah = null;
            $flag_ganti_rumah = null;
            $flag_ganti_cuti = null;
            $flag_homebase = null;
            $flag_insentif = 1;
            $flag_ket_kerja = 1;

            //get data terkait salary seperti gaji dasar dan tunjangan lainnya
            $salary = Model::getSalaryData($bi,$data_ba->new_bp);
        
            //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
            if($data_ba->positionNew->structural == "Y"){
                $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                $flag_tunjangan_jabatan = 1;
                $strukctural_data = $data_ba->positionNew->structural;
                $functional_data = null;
            }elseif($data_ba->positionNew->functional == "Y"){
                $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                $flag_tunjangan_jabatan = 1;
                $strukctural_data = null;
                $functional_data = $data_ba->positionNew->functional;
            }elseif(strpos($data_ba->new_title, 'Senior Staff') !== false || ($data_ba->band == 1 && $data_ba->level_band <= 1) || strpos($data_ba->new_title, 'Senior Advisor Associate') !== false || strpos($data_ba->new_title, 'Advisor Associate') !== false || strpos($data_ba->new_title, 'Telkomsel Next Gen Associate') !== false || strpos($data_ba->new_title, 'Senior Associate') !== false) { //sini buru
                $flag_tunjangan_jabatan = 0;
                $strukctural_data = null;
                $functional_data = null;
            }else{
                if($data_ba->employee->structural == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                    $flag_tunjangan_jabatan = 1;
                    $strukctural_data = $data_ba->employee->structural;
                    $functional_data = null;
                }elseif($data_ba->employee->functional == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                    $flag_tunjangan_jabatan = 1;
                    $strukctural_data = null;
                    $functional_data = $data_ba->employee->functional;
                }else{
                    $flag_tunjangan_jabatan = 0;
                    $strukctural_data = null;
                    $functional_data = null;
                }
            }
            
            //var_dump($data_ba->new_title);exit;
            
            /*
            if(strpos($data_ba->new_title, 'Senior Staff') !== false || ($data_ba->band == 1 && $data_ba->level_band <= 1) || strpos($data_ba->new_title, 'Senior Advisor Associate') !== false || strpos($data_ba->new_title, 'Advisor Associate') !== false || strpos($data_ba->new_title, 'Telkomsel Next Gen Associate') !== false || strpos($data_ba->new_title, 'Senior Associate') !== false) { //sini buru
                $flag_tunjangan_jabatan = 0;
            } else {
                $flag_tunjangan_jabatan = 1;
            }
            */
            
            
            
            $flag_gaji_dasar_nss = 0;
            if($data_ba->gaji_dasar_nss >= 0 && !empty($data_ba->gaji_dasar_nss)) {
                $flag_gaji_dasar_nss = 1;
            }
            

            //var_dump($flag_tunjangan_jabatan, $data_ba->new_title);exit;
            $content_sk = Model::generateEsk($data_esk_master['id'],$data_ba->nik,$data_ba->flag_kisel,$data_ba->last_payroll,"",$flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja, null, $flag_tunjangan_jabatan);
            //check apakah content ada code periode atau nodin 
            if( (strpos($content_sk,"{periode}") !== false && empty($periode) && empty($data_ba->periode)) 
            || (strpos($content_sk,"{nota_dinas}") !== false && empty($nodin) && empty($data_ba->nota_dinas))
            || (strpos($content_sk,"{nama_penyakit}") !== false && empty($nama_penyakit) && empty($data_ba->nama_penyakit))
            || (strpos($content_sk,"{nominal_insentif}") !== false && empty($nominal_insentif) && empty($data_ba->nominal_insentif))
            || (strpos($content_sk,"{manual_content_1}") !== false && empty($manual_content_1))
            || (strpos($content_sk,"{manual_content_2}") !== false && empty($manual_content_2))
            || (strpos($content_sk,"{manual_content_3}") !== false && empty($manual_content_3))
            || (strpos($content_sk,"{keterangan_ba_1}") !== false && empty($keterangan_ba_1) && empty($data_ba->keterangan_ba_1))
            || (strpos($content_sk,"{keterangan_ba_2}") !== false && empty($keterangan_ba_2) && empty($data_ba->keterangan_ba_2)) 
            || (strpos($content_sk,"{keterangan_ba_3}") !== false && empty($keterangan_ba_3) && empty($data_ba->keterangan_ba_3)) 
            || (strpos($content_sk,"{keputusan_direksi_1}") !== false && empty($keputusan_direksi_1) && empty($data_ba->keputusan_direksi_1))
            || (strpos($content_sk,"{keputusan_direksi_2}") !== false && empty($keputusan_direksi_2) && empty($data_ba->keputusan_direksi_2)) 
            || (strpos($content_sk,"{keputusan_direksi_3}") !== false && empty($keputusan_direksi_3) && empty($data_ba->keputusan_direksi_3))
            || (strpos($content_sk,"{tunjangan_hot_skill}") !== false && empty($tunjangan_hot_skill) && empty($data_ba->tunjangan_hot_skill))
            || (strpos($content_sk,"{new_nik}") !== false && empty($new_nik) && empty($data_ba->new_nik))
            ){
                $flag_periode = strpos($content_sk,"{periode}") !== false ? '1' : '0';
                $flag_nodin = strpos($content_sk,"{nota_dinas}") !== false ? '1' : '0';
                $flag_nama_penyakit = strpos($content_sk,"{nama_penyakit}") !== false ? '1' : '0';
                $flag_nominal_insentif = strpos($content_sk,"{nominal_insentif}") !== false ? '1' : '0';
                $flag_content_1 = strpos($content_sk,"{manual_content_1}") !== false ? '1' : '0';
                $flag_content_2 = strpos($content_sk,"{manual_content_2}") !== false ? '1' : '0';
                $flag_content_3 = strpos($content_sk,"{manual_content_3}") !== false ? '1' : '0';
                $flag_ba_1 = strpos($content_sk,"{keterangan_ba_1}") !== false ? '1' : '0';
                $flag_ba_2 = strpos($content_sk,"{keterangan_ba_2}") !== false ? '1' : '0';
                $flag_ba_3 = strpos($content_sk,"{keterangan_ba_3}") !== false ? '1' : '0';
                $flag_kd_1 = strpos($content_sk,"{keputusan_direksi_1}") !== false ? '1' : '0';
                $flag_kd_2 = strpos($content_sk,"{keputusan_direksi_2}") !== false ? '1' : '0';
                $flag_kd_3 = strpos($content_sk,"{keputusan_direksi_3}") !== false ? '1' : '0';
                $flag_tunjangan_hot_skill = strpos($content_sk,"{tunjangan_hot_skill}") !== false ? '1' : '0';
                $flag_new_nik = strpos($content_sk,"{new_nik}") !== false ? '1' : '0';
                
                $data_return = array(
                    "result" => "failed",
                    "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because some addtional content (BA/KD/Periode/Nota Dinas/Diseases Name/Insentif Amount) is empty!"
                );
            }

            //validasi effective date dan flag backdate             
            $today = date("Y-m-d");
   
            if(!empty($data_ba->effective_date)){
                $selisih = strtotime($data_ba->effective_date) - strtotime($today);
                $days = floor($selisih / (60*60*24));
                if($days < 0 ){
                    $flag_backdate = 1;
                }else{
                    $flag_backdate = 0;
                }
                $new_effective_date = $data_ba->effective_date;
                $new_flag_backdate = $flag_backdate;
            }else{
                $selisih = strtotime($tgl_berlaku) - strtotime($today);
                $days = floor($selisih / (60*60*24));
                if($days < 0 ){
                    $flag_backdate = 1;
                }else{
                    $flag_backdate = 0;
                }

                $new_effective_date = $tgl_berlaku;
                $new_flag_backdate = $flag_backdate;
            }

            //replace data content sknya lepar juga tgl_berlaku_sk
            $replace_sk = Model::replaceBA($ba,$data_esk_master['id'],$new_effective_date,$content_sk,$salary,$data_esk_master['decree_title'],$data_esk_master['authority'],$periode,$nodin,$manual_content_1,$manual_content_2,$manual_content_3,$keterangan_ba_1,$keterangan_ba_2,$keterangan_ba_3,$keputusan_direksi_1,$keputusan_direksi_2,$keputusan_direksi_3,$nama_penyakit,$nominal_insentif,$temp);

            //get atasan
            if(strpos($data_ba->tipe,"Position Applied from Exchange") !== false){
                $data_posisi_new = Position::findOne($data_ba->new_position_id);
                if(!empty($data_posisi_new)){
                    //get data employee dengan posisition_id
                    $data_employee_new = Employee::find()->where(['position_id' => $data_ba->new_position_id])->one();
                    if(!empty($data_employee_new)){
                        $atasan_array = Model::getHead($data_employee_new->nik);
                        $atasan = implode(";",$atasan_array);
                    }else{
                        $atasan_array = Model::getHead($data_ba->nik);
                        $atasan = implode(";",$atasan_array);
                    }
                }else{
                    $atasan_array = Model::getHead($data_ba->nik);
                    $atasan = implode(";",$atasan_array);
                }
            }else{
                $atasan_array = Model::getHead($data_ba->nik);
                $atasan = implode(";",$atasan_array);
            }

            if(!empty($atasan_array)){
                foreach($atasan_array as $emp){
                    $data_emp = Employee::find()->where(['nik' => $emp])->one();
                    if((strpos(strtolower($data_emp->job_category), "vice president") !== false && ($dataoldbp[0] != 5 || $dataoldbp[0] != 6)) || strpos(strtolower($data_emp->job_category), "director") !== false){
                        if($dataoldbp[0] == 5 || $dataoldbp[0] == 6){
                            //get data gm hcm HO
                            $vp_nik = Model::getGMhcbp($data_esk_master['authority']);
                        }else{
                            $vp_nik = $emp;
                        }
                        break;
                    }else{
                        $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
                    }
                }
            }else{
                $data_emp = Employee::find()->where(['nik' => $emp])->one();
                $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
            }

            //get data employee 
            $emp_ba = Employee::find()->where(['nik' => $data_ba->nik])->one();

            //set dan save data esk
            $model->attributes = $data_ba->attributes;
            $model->id_ba_detail = $ba;
            if($temp == null){
                $model->nomor_ba = null;
                $model->ba_date = null;
            }else{
                $model->nomor_ba = $data_ba->beritaAcaras->no;
                $model->ba_date = $data_ba->beritaAcaras->ba_date;
            }
            $model->about_esk = $data_esk_master['about'];
            $model->no_esk = $data_esk_master['number_esk']; //dari esk template authority
            $model->content_esk = $replace_sk;
            $model->effective_esk_date = $new_effective_date;
            $model->flag_backdate = $new_flag_backdate;
            $model->head_nik = $atasan;
            $model->vp_nik = $vp_nik;
            $model->status = "generated"; //ubah jika sudah bisa approval jadi 'generate' pertama kalinya
            $model->tracking = "Generated"; //ubah sesuai dengan approvalnya nanti
            $model->created_by = Yii::$app->user->identity->id;
            $model->id_template_master = $data_esk_master['id'];
            $model->batch_name = $batch_name;
            $model->id_batch = $batch_process;
            $model->old_position = $data_ba->old_title; //(!empty($data_ba->positionOld)) ? $data_ba->positionOld->nama : $data_ba->old_title;
            $model->new_position = $data_ba->new_title; //(!empty($data_ba->positionNew)) ? $data_ba->positionNew->nama : $data_ba->new_title;
            $model->mutasi = $dataMED['mutasi'];
            $model->eva = $dataMED['eva'];
            $model->dpe_ba = $data_ba->dpe;
            $model->dpe = $dataMED['dpe'];
            $model->authority = $data_esk_master['authority']; //dari esk template authority
            $model->structural = $strukctural_data;
            $model->functional = $functional_data;
            $model->gaji_dasar = $salary['gaji_dasar'];
            $model->tunjangan_biaya_hidup = $salary['tunjangan_biaya_hidup'];
            $model->tunjangan_jabatan = $salary['tunjangan_jabatan'];
            $model->tunjangan_fungsional = $salary['tunjangan_fungsional'];
            $model->tunjangan_rekomposisi = $salary['tunjangan_rekomposisi'];
            $model->level_tbh = $data_ba->new_bi;
            $model->level_tr = $data_ba->new_bi;

            // perubahan approval mpp dan sakit berkepanjangan (all band) - 12092024 -ejes                                
            //if((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) && $model->level_band <= 4){
            if ( strpos(strtolower($data_ba->tipe), "sakit berkepanjangan") !== FALSE || strpos(strtolower($data_ba->tipe), "mpp") !== FALSE  )
                  {
                        $model->decree_nama = "Indrawan Ditapradana";
                        $model->decree_nik = "7310004";
                        $model->decree_title = "Director Human Capital Management";
                        $model->represented_title = "Direksi Perseroan";                
            }else{

                if((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false ) && $model->level_band <= 4){
                    // PJS
                    $model->decree_nama = "Indrawan Ditapradana";
                    $model->decree_nik = "7310004";
                    $model->decree_title = "Director Human Capital Management";
                    $model->represented_title = "Direksi Perseroan";
                //} elseif((strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) && $model->level_band >= 5){
                } elseif((strpos(strtolower($model->tipe), "mutasi aps") !== false ) && $model->level_band >= 5){
                    $model->decree_nama = "Nugroho";
                    $model->decree_nik = "7610001";
                    $model->decree_title = "President Director";
                    $model->represented_title = "Direksi Perseroan";
                } else {
                    $model->decree_nama = $data_esk_master['decree_nama'];
                    $model->decree_nik = $data_esk_master['decree_nik'];
                    $model->decree_title = $data_esk_master['decree_title'];
                    $model->represented_title = $data_esk_master['represented_title'];
                }
            }
            $model->is_represented = $data_esk_master['is_represented'];
            $model->city_esk = $data_esk_master['city_esk'];
            $model->file_name = $data_esk_master['file_name'];
            $model->nota_dinas = (empty($data_ba->nota_dinas)) ? $nodin : $data_ba->nota_dinas;
            $model->periode = (empty($data_ba->periode)) ? $periode : $data_ba->periode;
            $model->nama_penyakit = (empty($data_ba->nama_penyakit)) ? $nama_penyakit : $data_ba->nama_penyakit;
            $model->nominal_insentif = (empty($data_ba->nominal_insentif)) ? $nominal_insentif : $data_ba->nominal_insentif;
            $model->manual_content_1 = $manual_content_1;
            $model->manual_content_2 = $manual_content_2;
            $model->manual_content_3 = $manual_content_3;
            $model->keterangan_ba_1 =(empty($data_ba->keterangan_ba_1)) ? $keterangan_ba_1 : $data_ba->keterangan_ba_1;
            $model->keterangan_ba_2 = (empty($data_ba->keterangan_ba_2)) ? $keterangan_ba_2 : $data_ba->keterangan_ba_2;
            $model->keterangan_ba_3 = (empty($data_ba->keterangan_ba_3)) ? $keterangan_ba_3 : $data_ba->keterangan_ba_3;
            $model->keputusan_direksi_1 = (empty($data_ba->keputusan_direksi_1)) ? $keputusan_direksi_1 : $data_ba->keputusan_direksi_1;
            $model->keputusan_direksi_2 = (empty($data_ba->keputusan_direksi_2)) ? $keputusan_direksi_2 : $data_ba->keputusan_direksi_2;
            $model->keputusan_direksi_3 = (empty($data_ba->keputusan_direksi_3)) ? $keputusan_direksi_3 : $data_ba->keputusan_direksi_3;
            $model->alamat = (!empty($emp_ba)) ? $emp_ba->alamat : "";

            //set data creator
            if((!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee))){
                if(Yii::$app->user->can('sysadmin')){
                    $model->created_authority = "HEAD OFFICE";
                    $model->head_created = Employee::find()
                    ->join('JOIN','user','user.nik = employee.nik')
                    ->join('JOIN','auth_assignment','auth_assignment.user_id = user.id')
                    ->where(['auth_assignment.item_name' => 'hc_staffing', 'employee.job_category' => 'manager'])->one()->nik;
                }else{
                    $model->created_authority =Yii::$app->user->identity->employee->area;
                    $model->head_created = Yii::$app->user->identity->employee->nik_atasan;
                }
            }else{
                $model->created_authority = "HEAD OFFICE";
                $model->head_created = Employee::find()
                ->join('JOIN','user','user.nik = employee.nik')
                ->join('JOIN','auth_assignment','auth_assignment.user_id = user.id')
                ->where(['auth_assignment.item_name' => 'hc_staffing', 'employee.job_category' => 'manager'])->one()->nik;
            }
            $model->flag_ack_seq = 1;
            
            //set  atasan
            // $model->atasan_created = Yii::$app->user->identity->employee->nik_atasan;
            
            if($model->save()){
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Generate eSK with ID ".$model->id);

                $data_return = array(
                    "result" => "success",
                    "remark" => ""
                );
            }else{
                $error = implode(",",$model->getErrorSummary(true));

                $data_return = array(
                    "result" => "failed",
                    "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because ".$error
                );
            }
        }else{
            $data_return = array(
                "result" => "failed",
                "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." template not found, please check again (type, code, band, old area and new area) or decree by"
            );
        }

        return $data_return;
    }

    public function regenerateEsk($ba,$temp){
        //inisialisasi awal
        set_time_limit(0);
        ini_set('memory_limit', '2048M');
        $model = EskListsTemp::find()->where(['id_ba_detail' => $ba])->one();

        if($temp == null){
            $data_ba = EskBeritaAcaraDetailOtherTemp::find()->where(['id' => $ba])->one();
        }else{
            $data_ba = EskBeritaAcaraDetailOther::find()->where(['id' => $ba])->one();
        }

        if(!empty($data_ba->new_bi)){
            $bi = $data_ba->new_bi;
        }else{
            $bi = $data_ba->old_bi;
        }

        //get data mutasi, eva dan dpe 
        $dataMED = Model::getMutasiEvaDpe($data_ba->old_kota, $data_ba->new_kota, $data_ba->old_kode_kota, $data_ba->new_kode_kota, $data_ba->tipe, $data_ba->dpe);

        //explode data BP 
        $databp = explode(".",$data_ba->new_bp);                
        $dataoldbp = explode(".",$data_ba->old_bp);   
        
        if(is_null($databp[0]) == true || $databp[0] == "") {
            $dataemployee = Employee::findOne(['nik' => $data_ba->nik]);
            $databp = explode(".",$dataemployee->bp);    
        }
        
        //search tipe ba di template esk master
        $data_esk_master = Model::checkTemplateMaster($data_ba->tipe,$data_ba->code_template,$databp[0],$data_ba->old_area,$data_ba->new_area,$data_ba->old_directorate);
        if(!empty($data_esk_master)){
            //default value
            $flag_gaji = 1;
            $flag_uang_pisah = null;
            $flag_ganti_rumah = null;
            $flag_ganti_cuti = null;
            $flag_homebase = null;
            $flag_insentif = 1;
            $flag_ket_kerja = 1;

            //get data terkait salary seperti gaji dasar dan tunjangan lainnya
            $salary = Model::getSalaryData($bi,$data_ba->new_bp);
        
            //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
            if($data_ba->positionNew->structural == "Y"){
                $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                $strukctural_data = $data_ba->positionNew->structural;
                $functional_data = null;
            }elseif($data_ba->positionNew->functional == "Y"){
                $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                $strukctural_data = null;
                $functional_data = $data_ba->positionNew->functional;
            }else{
                if($data_ba->employee->structural == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                    $strukctural_data = $data_ba->employee->structural;
                    $functional_data = null;
                }elseif($data_ba->employee->functional == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                    $strukctural_data = null;
                    $functional_data = $data_ba->employee->functional;
                }else{
                    $flag_tunjangan_jabatan = 0;
                    $strukctural_data = null;
                    $functional_data = null;
                }
            }
            
            
            $content_sk = Model::generateEsk($data_esk_master['id'],$data_ba->nik,$data_ba->flag_kisel,$data_ba->last_payroll,"",$flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja, null, $flag_tunjangan_jabatan);
            //validasi effective date dan flag backdate             
            $today = date("Y-m-d");
   
            if(!empty($data_ba->effective_date)){
                $selisih = strtotime($data_ba->effective_date) - strtotime($today);
                $days = floor($selisih / (60*60*24));
                if($days < 0 ){
                    $flag_backdate = 1;
                }else{
                    $flag_backdate = 0;
                }
                $new_effective_date = $data_ba->effective_date;
                $new_flag_backdate = $flag_backdate;
            }else{
                $selisih = strtotime($tgl_berlaku) - strtotime($today);
                $days = floor($selisih / (60*60*24));
                if($days < 0 ){
                    $flag_backdate = 1;
                }else{
                    $flag_backdate = 0;
                }

                $new_effective_date = $tgl_berlaku;
                $new_flag_backdate = $flag_backdate;
            }

            //replace data content sknya lepar juga tgl_berlaku_sk
            $replace_sk = Model::replaceBA($ba,$data_esk_master['id'],$new_effective_date,$content_sk,$salary,$data_esk_master['decree_title'],$data_esk_master['authority'],$periode,$nodin,$manual_content_1,$manual_content_2,$manual_content_3,$keterangan_ba_1,$keterangan_ba_2,$keterangan_ba_3,$keputusan_direksi_1,$keputusan_direksi_2,$keputusan_direksi_3,$nama_penyakit,$nominal_insentif,$temp);
            //get atasan
            if(strpos($data_ba->tipe,"Position Applied from Exchange") !== false){
                $data_posisi_new = Position::findOne($data_ba->new_position_id);
                if(!empty($data_posisi_new)){
                    //get data employee dengan posisition_id
                    $data_employee_new = Employee::find()->where(['position_id' => $data_ba->new_position_id])->one();
                    if(!empty($data_employee_new)){
                        $atasan_array = Model::getHead($data_employee_new->nik);
                        $atasan = implode(";",$atasan_array);
                    }else{
                        $atasan_array = Model::getHead($data_ba->nik);
                        $atasan = implode(";",$atasan_array);
                    }
                }else{
                    $atasan_array = Model::getHead($data_ba->nik);
                    $atasan = implode(";",$atasan_array);
                }
            }else{
                $atasan_array = Model::getHead($data_ba->nik);
                $atasan = implode(";",$atasan_array);
            }

            if(!empty($atasan_array)){
                foreach($atasan_array as $emp){
                    $data_emp = Employee::find()->where(['nik' => $emp])->one();
                    if((strpos(strtolower($data_emp->job_category), "vice president") !== false && ($dataoldbp[0] != 5 || $dataoldbp[0] != 6)) || strpos(strtolower($data_emp->job_category), "director") !== false){
                        if($dataoldbp[0] == 5 || $dataoldbp[0] == 6){
                            //get data gm hcm HO
                            $vp_nik = Model::getGMhcbp($data_esk_master['authority']);
                        }else{
                            $vp_nik = $emp;
                        }
                        break;
                    }else{
                        $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
                    }
                }
            }else{
                $data_emp = Employee::find()->where(['nik' => $emp])->one();
                $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
            }

            //get data employee 
            $emp_ba = Employee::find()->where(['nik' => $data_ba->nik])->one();

            //set dan save data esk
            $model->attributes = $data_ba->attributes;
            $model->id_ba_detail = $ba;
            if($temp == null){
                $model->nomor_ba = null;
                $model->ba_date = null;
            }else{
                $model->nomor_ba = $data_ba->beritaAcaras->no;
                $model->ba_date = $data_ba->beritaAcaras->ba_date;
            }
            $model->about_esk = $data_esk_master['about'];
            $model->no_esk = $data_esk_master['number_esk']; //dari esk template authority
            $model->content_esk = $replace_sk;
            $model->effective_esk_date = $new_effective_date;
            $model->flag_backdate = $new_flag_backdate;
            $model->head_nik = $atasan;
            $model->vp_nik = $vp_nik;
            $model->status = "generated"; //ubah jika sudah bisa approval jadi 'generate' pertama kalinya
            $model->tracking = "Generated"; //ubah sesuai dengan approvalnya nanti
            $model->created_by = Yii::$app->user->identity->id;
            $model->id_template_master = $data_esk_master['id'];
            $model->batch_name = $batch_name;
            $model->id_batch = $batch_process;
            $model->old_position = $data_ba->old_title; //(!empty($data_ba->positionOld)) ? $data_ba->positionOld->nama : $data_ba->old_title;
            $model->new_position = $data_ba->new_title; //(!empty($data_ba->positionNew)) ? $data_ba->positionNew->nama : $data_ba->new_title;
            $model->mutasi = $dataMED['mutasi'];
            $model->eva = $dataMED['eva'];
            $model->dpe_ba = $data_ba->dpe;
            $model->dpe = $dataMED['dpe'];
            $model->authority = $data_esk_master['authority']; //dari esk template authority
            $model->structural = $strukctural_data;
            $model->functional = $functional_data;
            $model->gaji_dasar = $salary['gaji_dasar'];
            $model->tunjangan_biaya_hidup = $salary['tunjangan_biaya_hidup'];
            $model->tunjangan_jabatan = $salary['tunjangan_jabatan'];
            $model->tunjangan_fungsional = $salary['tunjangan_fungsional'];
            $model->tunjangan_rekomposisi = $salary['tunjangan_rekomposisi'];
            $model->level_tbh = $data_ba->new_bi;
            $model->level_tr = $data_ba->new_bi;
            // perubahan approval mpp dan sakit berkepanjangan (all band) - 12092024 -ejes                                
           if ( strpos(strtolower($data_ba->tipe), "sakit berkepanjangan") !== FALSE || strpos(strtolower($data_ba->tipe), "mpp") !== FALSE  )
                {
                      $model->decree_nama = "Indrawan Ditapradana";
                      $model->decree_nik = "7310004";
                      $model->decree_title = "Director Human Capital Management";
                      $model->represented_title = "Direksi Perseroan";                
            }else{

                //if((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) && $model->level_band <= 4){
                if((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false ) && $model->level_band <= 4){
                    // PJS
                    $model->decree_nama = "Indrawan Ditapradana";
                    $model->decree_nik = "7310004";
                    $model->decree_title = "Director Human Capital Management";
                    $model->represented_title = "Direksi Perseroan";
                //} elseif((strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) && $model->level_band >= 5){
                } elseif((strpos(strtolower($model->tipe), "mutasi aps") !== false) && $model->level_band >= 5){
                    $model->decree_nama = "Nugroho";
                    $model->decree_nik = "7610001";
                    $model->decree_title = "President Director";
                    $model->represented_title = "Direksi Perseroan";
                } else {
                    $model->decree_nama = $data_esk_master['decree_nama'];
                    $model->decree_nik = $data_esk_master['decree_nik'];
                    $model->decree_title = $data_esk_master['decree_title'];
                }
            }

            $model->is_represented = $data_esk_master['is_represented'];
            $model->represented_title = $data_esk_master['represented_title'];
            $model->city_esk = $data_esk_master['city_esk'];
            $model->file_name = $data_esk_master['file_name'];
            $model->nota_dinas = (empty($data_ba->nota_dinas)) ? $nodin : $data_ba->nota_dinas;
            $model->periode = (empty($data_ba->periode)) ? $periode : $data_ba->periode;
            $model->nama_penyakit = (empty($data_ba->nama_penyakit)) ? $nama_penyakit : $data_ba->nama_penyakit;
            $model->nominal_insentif = (empty($data_ba->nominal_insentif)) ? $nominal_insentif : $data_ba->nominal_insentif;
            $model->manual_content_1 = $manual_content_1;
            $model->manual_content_2 = $manual_content_2;
            $model->manual_content_3 = $manual_content_3;
            $model->keterangan_ba_1 =(empty($data_ba->keterangan_ba_1)) ? $keterangan_ba_1 : $data_ba->keterangan_ba_1;
            $model->keterangan_ba_2 = (empty($data_ba->keterangan_ba_2)) ? $keterangan_ba_2 : $data_ba->keterangan_ba_2;
            $model->keterangan_ba_3 = (empty($data_ba->keterangan_ba_3)) ? $keterangan_ba_3 : $data_ba->keterangan_ba_3;
            $model->keputusan_direksi_1 = (empty($data_ba->keputusan_direksi_1)) ? $keputusan_direksi_1 : $data_ba->keputusan_direksi_1;
            $model->keputusan_direksi_2 = (empty($data_ba->keputusan_direksi_2)) ? $keputusan_direksi_2 : $data_ba->keputusan_direksi_2;
            $model->keputusan_direksi_3 = (empty($data_ba->keputusan_direksi_3)) ? $keputusan_direksi_3 : $data_ba->keputusan_direksi_3;
            $model->alamat = (!empty($emp_ba)) ? $emp_ba->alamat : "";

            if($model->save()){
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Regenerate eSK with ID ".$model->id);

                $data_return = array(
                    "result" => "success",
                    "remark" => ""
                );
                }else{
                $error = implode(",",$model->getErrorSummary(true));

                $data_return = array(
                    "result" => "failed",
                    "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because ".$error
                );
            }
        }else{
            $data_return = array(
                "result" => "failed",
                "remark" => "data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." template not found, please check again (type, code, band, old area and new area) or decree by"
            );
        }

        return $data_return;
    }

    public function setAcknowlegeLists($flag_deliver_to, $nik_atasan, $old_position, $new_position, $new_position_id, $authority, $id_esk, $new_directorate){
        //acknowledge ke business user
        if($flag_deliver_to == 1){
            $ack_array = Model::getHead($nik_atasan);
            $position_emp = $old_position;
        }elseif($flag_deliver_to == 3){
            //get data posisi terbarunya
            $data_posisi = Position::findOne($new_position_id);
            if(!empty($data_posisi)){
                //get data employee dengan posisition_id
                $data_employee = Employee::find()->where(['position_id' => $new_position_id])->one();
                if(!empty($data_employee)){
                    $ack_array = Model::getHead($data_employee->nik);
                }else{
                    $ack_array = Model::getHead($nik_atasan);
                }
                $position_emp = $new_position;
            }else{
                //set empty array
                $ack_array = array();
            }
        }

        if(!empty($ack_array)){
            $i_data = 1;
            foreach(array_reverse($ack_array) as $atasan){
                $emp = Employee::find()->where(['nik' => $atasan])->one();
                if(!empty($emp) &&
                (
                    //masukkan title director hanya jika posisi lama karyawan adalah executive vp atau vp    
                    (strpos(strtolower($emp->job_category),"director") !== false && (strpos(strtolower($position_emp),"executive vice president") !== false || strpos(strtolower($position_emp),"vice president") !== false)) ||
                    //masukkan title vice president hanya jika titlnye bukan executive vice president
                    (strpos(strtolower($emp->job_category),"vice president") !== false && strpos(strtolower($emp->job_category),"executive vice president") === false) || 
                    //masukkan title GM hanya jika title lamanya bukan GM
                    (strpos(strtolower($emp->title),"general manager") !== false && (strpos(strtolower($position_emp),"general manager") === false)) || 
                    //masukkan title manager hanya jika title lamanya bukan manager
                    (strpos(strtolower($emp->title),"manager") !== false && (strpos(strtolower($position_emp),"general manager") === false) && (strpos(strtolower($position_emp),"manager") === false)  ))
                ){  
                    if($dataoldbp[0] == 5 || $dataoldbp[0] == 6){
                        //get data gm hcm HO
                        $atasan = Model::getGMhcbp($authority);
                        $emp = Employee::find()->where(['nik' => $atasan])->one();
                    }

                    //save ada acknowledge
                    $data_a = new EskAcknowledgeLists();
                    $data_a->id_esk = $id_esk;
                    $data_a->ack_nik = $atasan;
                    $data_a->ack_name = $emp->nama;
                    $data_a->ack_mail = $emp->email;
                    $data_a->ack_title = $emp->title;
                    $data_a->sequence = $i_data;
                    $data_a->save();
                    
                    if($dataoldbp[0] == 5 || $dataoldbp[0] == 6){
                        break;
                    }
                    $i_data++;
                }
            }
        }
    }

    /**
     * Deletes an existing BeritaAcara model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    public function actionDeleteBeritaAcara($id)
    {   
        $model = EskBeritaAcaraOther::findOne($id);

        //count data 
        $data_detail = EskBeritaAcaraDetailOther::find()->where('id_master = "'.$id.'"')->all();

        // mulai database transaction
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            if($model->delete()){
                if(!empty($data_detail)){
                    //get data berita acara
                    foreach($data_detail as $detail){
                        $esk_temp = EskListsTemp::find()->where(['id_ba_detail' => $detail->id])->one();
                        if(!empty($esk_temp)){
                            if (!$esk_temp->delete()){
                                $transaction->rollBack();
                                Yii::$app->session->setFlash('error', "Berita Acara data was not deleted.");
                            }
                        }

                        //delete detail
                        if (!$detail->delete()){
                            $transaction->rollBack();
                            Yii::$app->session->setFlash('error', "Berita Acara data was not deleted.");
                        }
                    }
                    
                }

                $transaction->commit();

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Delete Berita Acara data with ID ".$id);
        
                Yii::$app->session->setFlash('success', "Berita Acara data successfully deleted."); 
            }else{
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', "Berita Acara data was not deleted.");
            }
        }catch (Exception $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', "Berita Acara data was not deleted because ".$e);
        }
        
        return $this->redirect(['index']);
    }


    /**
     * Finds the BeritaAcara model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return BeritaAcara the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BeritaAcara::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionGetDetail($batch_number,$number_ba = null, $temp = null){
        if($temp == null){
            $data_master = "";
            $data_detail = EskBeritaAcaraDetailOtherTemp::beritaAcaraDetail($batch_number);
        }else{
            $data_master =  EskBeritaAcaraOther::findOne($batch_number);
            $data_detail = EskBeritaAcaraDetailOther::beritaAcaraDetail($batch_number);
        }

        return $this->renderAjax('_berita_acara_detail', [
            'data_master' => $data_master,
            'dataProvider' => $data_detail,
            'batch_number' => $batch_number,
            'number_ba' => $number_ba,
            'temp' => $temp
        ]);
    }

    public function actionSaveDetail($batch_number,$nik,$position_id,$ba_date,$tipe,$code,$new_bi,
    $nota_dinas,$periode,$nama_penyakit,$nominal_insentif,$kd_1,$kd_2,$kd_3,$ba_1,$ba_2,$ba_3,$cltp_reason,$start_sick,$end_sick,
    $phk_date,$statement_date,$last_payroll,$resign_date,$scholarship_program,$scholarship_university,$scholarship_level,$flag_kisel,
    $senior_staff,$old_position,$new_position,$position_id_org, $new_city,$temp = null,$nik_new_atasan,$dpe_length,$dpe_unit,$grade){
        //check duplicate position by batch number
        if($temp == null){
            $count = EskBeritaAcaraDetailOtherTemp::find()->where(['batch_number' => $batch_number,'nik' => $nik])->count();
            $model = new EskBeritaAcaraDetailOtherTemp();
            $model->batch_number = $batch_number;
        }else{
            $count = EskBeritaAcaraDetailOther::find()->where(['id_master' => $batch_number,'nik' => $nik])->count();
            $model = new EskBeritaAcaraDetailOther();
            $model->id_master = $batch_number;
            
            $position = Position::find()->where(['id' => $position_id])->one();
            
        }

        //get data employee 
        $employee = Employee::find()->where(['nik' => $nik])->one();

        //get data position
        if(!empty($position_id) || $position_id == 101){
            if(($senior_staff == 1 || $senior_staff == "1") || strpos($tipe,"Exchange") !== false){
                $position = Position::find()->where(['id' => $position_id_org])->one();
                $position_id_baru = $position_id_org;
                $new_city = explode(":",$new_city);
                $kode_kota_baru = $new_city[0];
                $kota_baru = $new_city[1];
            }else{
                $position = Position::find()->where(['id' => $position_id])->one();
                $position_id_baru = $position_id;
                $kode_kota_baru = $position->city;
                $kota_baru = $position->desc_city;
            }
        }else{
            $position_id = $employee->position_id;
            $position_id_baru = $position_id;
            $position = Position::find()->where(['id' => $employee->position_id])->one();
            
        }
        
        //validasi senior staff
        if(($senior_staff == 1 || $senior_staff == "1") && (!empty($position_id) || $position_id == 101)){
            //replace posisi
            $position_baru = Model::replaceSeniorStaff($position->organization);
            $is_senior_staff = 1;
        }else{
            //cek apakah tipe Exchange
            if(strpos($tipe,"Exchange") !== false && !empty($new_position)){
                $position_baru = $new_position;
            }else{
                $position_baru = $position->nama;
            }
            $is_senior_staff = 0;
        }

        //validasi posisi lama dan tipe from Exchange
        if(strpos($tipe,"from Exchange") !== false && !empty($old_position)){
            $position_lama = $old_position;
        }else{
            $position_lama = $employee->title;
        }
        
        //validasi data bp posisi
        if(empty($position->bp)){
            $data = array(
                "result" => 0,
                "remark" => "Failed save, data BP position is empty from master position!"
            );

            return json_encode($data);
        }

        //update strukctural data
        if($position->structural == "Y"){
            $strukctural_data = $position->structural;
            $functional_data = null;
        }elseif($position->functional == "Y"){
            $strukctural_data = null;
            $functional_data = $position->functional;
        }else{
            if($employee->structural == "Y"){
                $strukctural_data = $employee->structural;
                $functional_data = null;
            }elseif($employee->functional == "Y"){
                $strukctural_data = null;
                $functional_data = $employee->functional;
            }else{
                $strukctural_data = null;
                $functional_data = null;
            }
        }

        //cek structural data 
        if($count <= 0){
            if (strpos($tipe, 'Promosi') !== false && $position->structural == "Y" && $position->band >= 2) {
                $position_baru = 'Pj. ' . $position_baru;
            } 
                            
            $model->person_id = $employee->person_id;
            $model->nik = $nik;
            $model->nama = $employee->nama;
            $model->is_senior_staff = $is_senior_staff;
            $model->old_position_id = $employee->position_id;
            $model->new_position_id = $position_id_baru;
            $model->old_title = $position_lama;
            $model->old_area = $employee->area;
            $model->old_bgroup = $employee->bgroup;
            $model->old_bi = $employee->bi;
            $model->old_bp = $employee->bp;
            $model->old_department = $employee->department;
            $model->old_directorate = $employee->directorate;
            $model->old_division = $employee->division;
            $model->old_egroup = $employee->egroup;
            $model->old_kode_kota = $employee->kode_kota;
            $model->old_kota = $employee->kota;
            $model->old_organization = $employee->organization;
            $model->old_section = $employee->section;
            $model->old_region = $employee->admins;
            $model->new_area = $position->area;
            $model->new_bgroup = $position->grp;
            $model->new_bp = $position->bp;
            $model->new_bi = $new_bi;
            $model->new_department = $position->department;
            $model->new_directorate = $position->directorate;
            $model->new_division = $position->division;
            $model->new_egroup = $position->egrp;
            $model->new_kode_kota = $kode_kota_baru;
            $model->new_kota = $kota_baru;
            $model->new_organization = $position->organization;
            $model->new_section = $position->section;
            $model->new_title = $position_baru;
            $model->new_region = $position->region;
            $model->code_template = $code;
            $model->structural =  $strukctural_data;
            $model->functional = $functional_data;
            $model->effective_date = date("Y-m-d",strtotime($ba_date));
            $model->tipe = $tipe;
            $model->nota_dinas = $nota_dinas;
            $model->periode = $periode;
            $model->nama_penyakit = $nama_penyakit;
            $model->nominal_insentif = (empty($nominal_insentif)) ? $nominal_insentif : str_replace(",","",$nominal_insentif);
            $model->keputusan_direksi_1 = $kd_1;
            $model->keputusan_direksi_2 = $kd_2;
            $model->keputusan_direksi_3 = $kd_3;
            $model->keterangan_ba_1 = $ba_1;
            $model->keterangan_ba_2 = $ba_2;
            $model->keterangan_ba_3 = $ba_3;
            $model->cltp_reason = $cltp_reason;
            $model->start_date_sick = (empty($start_sick)) ? $start_sick : date("Y-m-d",strtotime($start_sick));
            $model->end_date_sick = (empty($end_sick)) ? $end_sick : date("Y-m-d",strtotime($end_sick));
            $model->phk_date = (empty($phk_date)) ? $phk_date : date("Y-m-d",strtotime($phk_date));
            $model->tanggal_td_pernyataan = (empty($statement_date)) ? $statement_date : date("Y-m-d",strtotime($statement_date));
            $model->last_payroll = (empty($last_payroll)) ? $last_payroll : date("Y-m-d",strtotime($last_payroll));
            $model->resign_date = (empty($resign_date)) ? $resign_date : date("Y-m-d",strtotime($resign_date));
            $model->scholarship_program = $scholarship_program;
            $model->scholarship_university = $scholarship_university;
            $model->scholarship_level = $scholarship_level;
            $model->flag_kisel = $flag_kisel;
            // add by faqih sprint 3
            $model->nik_new_atasan = $nik_new_atasan;
            $model->dpe_length = $dpe_length;
            $model->dpe_unit = $dpe_unit;
            // add by faqih note FRS grade
            $model->grade = trim(ucwords($grade));

            $sql = "SELECT * FROM TSEL_HR_GRADE_V WHERE grade = '".$model->grade."'";
            $conn = Yii::$app->dbOra;
            $commandOra = $conn->createCommand($sql)
            ->queryOne();
            if(empty($commandOra)){
                $data = array(
                    "result" => 0,
                    "remark" => "Failed save, data Grade is empty from master Grade EBS!"
                );

                return json_encode($data);
            }

            // add by faqih
            $model->level_band = $position->band;
            $model->level_gaji = substr($employee->bi,0,1);

            // if($position->band <= 3) {
            //     $model->flag_ba_manager     = 1;
            //         $model->nik_approved_ba     = Yii::$app->user->identity->employee->nik_atasan;
            // } elseif($position->band >=4) {
            //     $model->flag_ba_manager     = 1;
            //     $model->nik_approved_ba     = Yii::$app->user->identity->employee->nik_atasan;
            //     //'86149'; approval pertama ke mas arnold
            // }

             // perubahan approval mpp dan sakit berkepanjangan - 12092024 -ejes
             if (strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false || strpos(strtolower($model->tipe), "mpp") !== false) {
                $model->flag_ba_manager     = 1;
                $model->nik_approved_ba     = '86149'; // approval pertama ke mas arnold
           
             }else{  
                if($model->level_band <= 3) {
                    $model->flag_ba_manager         = 1;
                    //$model->nik_approved_ba   = $this->getHeadCreator();
                    //find so
                    $so = EskSo::find()->where(['nik' => Yii::$app->user->identity->employee->nik])->andWhere(['directorate' => $model->old_directorate])->one();
                    if(!empty($so)) {
                        $model->nik_approved_ba = $so->so_nik;
                    } else {
                        $model->nik_approved_ba = Yii::$app->user->identity->employee->nik_atasan;
                    }
                
                } elseif($model->level_band >=4) {
                    $model->flag_ba_manager     = 1;
                    $model->nik_approved_ba     = '86149'; // approval pertama ke mas arnold
                }
             }

            
            
            $model->created_by = Yii::$app->user->identity->nik;
            // end

            if ($model->save()) {
                if($temp == null){
                    $model_2 = EskBeritaAcaraDetailOtherTemp::findOne($model->id);
                }else{
                    $model_2 = EskBeritaAcaraDetailOther::findOne($model->id);
                }

                //check eSK
                $check_esk_data = $this->checkGenerateEsk($model->id,$temp);
                if($check_esk_data['result'] == "success"){
                    //generate eSK 
                    $generate_data = $this->generateEsk($model->id,$temp);
                    if($generate_data['result'] == "success"){
                        $data = array(
                            "result" => 1,
                            "remark" => "Success",
                        );
                    }else{
                        $data = array(
                            "result" => 0,
                            "remark" => "Failed save data because data BA ".$generate_data['remark'],
                        );

                        //delete data
                        $model_2->delete();
                    }
                }else{
                    $data = array(
                        "result" => 0,
                        "remark" => "Failed save data because data BA ".$check_esk_data['remark'],
                    );

                    //delete data
                    $model_2->delete();
                }  
            }else{
                $error = implode(",",$model->getErrorSummary(true));
                $data = array(
                    "result" => 0,
                    "remark" => "Failed save data because ".$error."!",
                );
            }
        }else{
            $data = array(
                "result" => 0,
                "remark" => "Failed save, data already exist!"
            );
        }

        return json_encode($data);
    }

    public function actionDeleteDetail($batch_number,$temp = null){
        if($temp == null){
            $model = EskBeritaAcaraDetailOtherTemp::findOne($batch_number);
        }else{
            $model = EskBeritaAcaraDetailOther::findOne($batch_number);
        }
        $esk_lists = EskListsTemp::find()->where(['id_ba_detail' => $model->id])->one();

        $transaction = \Yii::$app->db->beginTransaction();
        if(!empty($model)){
            $transaction->commit();
            if ($model->delete()) {
                //delete esk_lists_temp
                if($esk_lists->delete()){
                    $data = array(
                        "result" => 1,
                        "remark" => "Success",
                    );
                }else{
                    $transaction->rollBack();
                    $error = implode(",",$model->getErrorSummary(true));
                    $data = array(
                        "result" => 0,
                        "remark" => "Failed cancel data because ".$error."!",
                    );
                }
            } else {
                $error = implode(",",$model->getErrorSummary(true));
                $data = array(
                    "result" => 0,
                    "remark" => "Failed cancel data because ".$error."!",
                );
            }
        }else{
            $data = array(
                "result" => 0,
                "remark" => "Data already deleted!",
            );
        }
        return json_encode($data);
    }

    public function actionViewDetail($batch_number,$temp = null){
        if($temp == null){
            $model = EskBeritaAcaraDetailOtherTemp::findOne($batch_number);
        }else{
            $model = EskBeritaAcaraDetailOther::findOne($batch_number);
        }

        if(!empty($model)){
            $data = array(
                "result" => Model::viewBA($model),
                "remark" => "Success",
            );
        }else{
            $data = array(
                "result" => 0,
                "remark" => "Detail data not found!",
            );
        }
        return json_encode($data);
    }

    public function actionCountDetail($batch_number,$temp = null){
        if($temp == null){
            $model = EskBeritaAcaraDetailOtherTemp::find()->where(['batch_number' => $batch_number])->count();
        }else{
            $model = EskBeritaAcaraDetailOther::find()->where(['id_master' => $batch_number])->count();
        }

        if(!empty($model)){
            $data = array(
                "count_data" => $model,
                "remark" => "Success",
            );
        }else{
            $data = array(
                "count_data" => 0,
                "remark" => "Detail data not found!",
            );
        }
        return json_encode($data);
    }

    public function actionGetEmployeeDetail($nik){
        $model = Employee::find()->where(['nik' => $nik])->asArray()->one();
        
        if(!empty($model)){
            $data = array(
                "data" => $model,
                "result" => 1,
            );
        }else{
            $data = array(
                "data" => "",
                "result" => 0,
            );
        }
        return json_encode($data);
    }

    public function actionGetCodeTemplate($position_id, $nik, $new_city = null){
        $data_position = Position::findOne($position_id);
        $data_employee = Employee::find()->where(['nik' => $nik])->one();
        $new_city_data = (empty($new_city)) ? "" : explode(";",$new_city);
        $kota_baru = (empty($new_city_data)) ? "" : $new_city_data[1];
        $data_jarak = EskJarak::find()->where(['kota_asal' => ucwords(strtolower($data_employee->kota)), 'kota_tujuan' => ucwords(strtolower($kota_baru)) ])->one();

        $band_emp = explode(".",$data_employee->bp);
        $band_pos = explode(".",$data_position->bp);

        //check apakah demosi
        if($data_employee->band > $data_position->band){
            $data = array(
                "result" => 0,
                "code_template" => "",
                "result_cat" => "",
                "remark" => "Please check again recommended position because employee will be demotion if choose it.",
            );
        }else{
            //check result category
            if($data_employee->band == $data_position->band && $data_employee->position_id == $data_position->id){
                $result_cat = 3;
                $code_bp = "ROT";
                $dpe = "";
            }elseif($data_employee->band == $data_position->band){
                $result_cat = 1;
                $code_bp = "ROT";
                $dpe = "";
            }else{
                $result_cat = 2;
                $code_bp = "PRO";
                $dpe = (empty($data_employee->dpe)) ? 0 : 1;
            }

            //check jarak
            if(!empty($data_jarak)){
                $jarak = 0;
            }elseif($data_jarak->jarak == 0){
                $jarak = 0;
            }elseif($data_jarak->jarak <= 100){
                $jarak = 1;
            }else{
                $jarak = 2;
            }

            //check grade
            $bp_emp = empty($band_emp[1]) ? $band_emp[0] : $band_emp[1];
            $bp_pos = empty($band_pos[1]) ? $band_pos[0] : $band_pos[1];
            if($bp_emp == $bp_pos){
                $grade = 0;
            }else{
                $grade = 1;
            }

            $data = array(
                "result" => 1,
                "code_template" => $code_bp."".$jarak."".$grade."".$dpe,
                "result_cat" => $result_cat,
                "remark" => "Success",
            );
        }

        return json_encode($data);
    }

    public function actionPreviewEsk($id,$nomor_ba = null)
    {   
        
        $whereNomorBa = empty($nomor_ba) ? 'nomor_ba IS NULL' : 'nomor_ba = "'.$nomor_ba.'"';
        $model = EskListsTemp::find()->where(['id_ba_detail' => $id])->andWhere($whereNomorBa)->one();
        $file_name = "";
        $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"preview","esk_ba");

        return $this->renderAjax('//staffing-lists/preview', [
            'content' => $all_content,
        ]);
    }

    // add by faqih get emp head (atasan)
    public function actionEmpheadlist($q = null, $id = null, $type = null) {
        //validasi status
        if(!empty($type)){
            if(strpos($type,"CLTP") !== false){
                $status = 'status = "AKTIF" || status = "TERMINATE"';
            }else{
                $status = 'status = "AKTIF"';
            }
        }else{
            $status = 'status = "AKTIF"';
        }

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(["nik AS id, CONCAT(nama, ' (', title, ')') AS text"])
                ->from('employee')
                ->where(['like', 'nama', $q])
                ->orWhere(['like', 'title', $q])
                ->andWhere($status);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Employee::find($id)->nama];
        }
        return $out;
    }

}
