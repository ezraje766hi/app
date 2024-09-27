<?php

namespace esk\controllers;

use Yii;
use esk\models\GenerateEsk;
use esk\models\GenerateEskSearch;
use esk\models\Position;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use esk\models\EskLists;
use esk\models\EskTemplateMaster;
use esk\models\Model;
use esk\models\EskCategory;
use esk\models\EskJarak;
use esk\models\EskApprovalLists;
use esk\models\EskApprovalMaster;
use esk\models\EskApprovalDetail;
use esk\models\Employee;
use esk\models\EskAcknowledgeLists;
use esk\models\EskAcknowledgeSettings;
use common\models\City;
use yii\data\ActiveDataProvider;

/**
 * GenerateEskController implements the CRUD actions for GenerateEsk model.
 */
class GenerateEskController extends Controller
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
                        'actions' => ['login', 'error'],
                        'allow' => true,
                    ],
                    [
                        'allow' => true,
                        'roles' => ['sysadmin','hc_staffing', 'hcbp_account'],
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
     * Lists all GenerateEsk models.
     * @return mixed
     */
    public function actionIndex()
    {   
		set_time_limit(0);
		ini_set('memory_limit', '2048M');
        $flag_all = yii::$app->request->get('flag_all');

        $searchModel = new GenerateEskSearch();
        if(Yii::$app->user->can('sysadmin') || $flag_all == "1"){
            //tampilkan semuanya
            $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
            //$flag_all = 1;
        }else{
            //get area user
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $user_area = Yii::$app->user->identity->employee->area;
            } else{
                $user_area = "N/A";
            }

            //get data array provider
            $ids = Model::getBeritaAcaraByArea($user_area);

            $dataProvider = $searchModel->findDetails($ids);
        }
		
		$dataProvider->pagination = false;
        
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'flag_all' => $flag_all,
        ]);
    }

    /**
     * Displays a single GenerateEsk model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->renderAjax('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionGenerate($id){
        set_time_limit(0);
		ini_set('memory_limit', '2048M');
        $model = new EskLists();

        if (Yii::$app->request->post()) {
           $request = Yii::$app->request->post();

           //inisialisasi data count 
            $countSuccess = 0;
            $countFailed = 0;
            $countAll = 0;
            $failed_array = array();

           //get data generate
           $id_ba_detail = explode(",",$request['id_ba_detail']);
           $tgl_berlaku = date("Y-m-d",strtotime($request['EskLists']['effective_esk_date']));
           $batch_name = $request['EskLists']['batch_name'];
           $periode = empty($request['EskLists']['periode']) ? '' : $request['EskLists']['periode'];  
           $nodin = empty($request['EskLists']['nota_dinas']) ? '' : $request['EskLists']['nota_dinas'];
           $nama_penyakit = empty($request['EskLists']['nama_penyakit']) ? '' : $request['EskLists']['nama_penyakit'];
           $nominal_insentif = empty($request['EskLists']['nominal_insentif']) ? '' : $request['EskLists']['nominal_insentif'];
           $manual_content_1 = empty($request['EskLists']['manual_content_1']) ? '' : $request['EskLists']['manual_content_1'];
           $manual_content_2 = empty($request['EskLists']['manual_content_2']) ? '' : $request['EskLists']['manual_content_2'];
           $manual_content_3 = empty($request['EskLists']['manual_content_3']) ? '' : $request['EskLists']['manual_content_3'];
           $keterangan_ba_1 = empty($request['EskLists']['keterangan_ba_1']) ? '' : $request['EskLists']['keterangan_ba_1'];
           $keterangan_ba_2 = empty($request['EskLists']['keterangan_ba_2']) ? '' : $request['EskLists']['keterangan_ba_2'];
           $keterangan_ba_3 = empty($request['EskLists']['keterangan_ba_3']) ? '' : $request['EskLists']['keterangan_ba_3'];
           $keputusan_direksi_1 = empty($request['EskLists']['keputusan_direksi_1']) ? '' : $request['EskLists']['keputusan_direksi_1'];
           $keputusan_direksi_2 = empty($request['EskLists']['keputusan_direksi_2']) ? '' : $request['EskLists']['keputusan_direksi_2'];
           $keputusan_direksi_3 = empty($request['EskLists']['keputusan_direksi_3']) ? '' : $request['EskLists']['keputusan_direksi_3'];
           $batch_process = Model::generateRandomString(); 
            
           //perulangan data sesuai id ba
           foreach($id_ba_detail as $ba){
                $data_ba = GenerateEsk::find()->where(['id' => $ba])->one();
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
                $data_esk_master = Model::checkTemplateMaster($data_ba->tipe,$data_ba->code_template,$databp[0],$data_ba->old_area,$data_ba->new_area,$data_ba->old_directorate);
                if(!empty($data_esk_master)){
                    //ada templatenya generate content esk sesuai data template master
                    //default value
                    $flag_gaji = 1;
                    $flag_uang_pisah = null;
                    $flag_ganti_rumah = null;
                    $flag_ganti_cuti = null;
                    $flag_homebase = null;
                    $flag_insentif = 1;
                    $flag_ket_kerja = 1;

                    //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
                    $content_sk = Model::generateEsk($data_esk_master['id'],$data_ba->nik,$data_ba->flag_kisel,$data_ba->last_payroll,"",$flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja, null, 1);

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

                        Yii::$app->session->setFlash('error', 'Please entry a periode/nota dinas/additional content!'); 

                        $model->id_ba_detail  = $id;
                        $model->batch_name = $batch_name;
                        $model->effective_esk_date = $request['EskLists']['effective_esk_date'];
                        $searchModel = new GenerateEskSearch();
                        $array_id = explode(",",$id);
                        $details = $searchModel->findDetails($array_id);
                        $count_proposed = $searchModel->countDetails($array_id);

                        return $this->render('generate', [
                            'model' => $model,
                            'id_ba' => $id,
                            'details' => $details,
                            'flag_periode' => $flag_periode,
                            'flag_nodin' => $flag_nodin,
                            'flag_nama_penyakit' => $flag_nama_penyakit,
                            'flag_nominal_insentif' => $flag_nominal_insentif,
                            'flag_content_1' => $flag_content_1,
                            'flag_content_2' => $flag_content_2,
                            'flag_content_3' => $flag_content_3,
                            'flag_ba_1' => $flag_ba_1,
                            'flag_ba_2' => $flag_ba_2,
                            'flag_ba_3' => $flag_ba_3,
                            'flag_kd_1' => $flag_kd_1,
                            'flag_kd_2' => $flag_kd_2,
                            'flag_kd_3' => $flag_kd_3,
                            'count_proposed' => $count_proposed,
                        ]);
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

                    //get data terkait salary seperti gaji dasar dan tunjangan lainnya
                    $salary = Model::getSalaryData($bi,$data_ba->new_bp);
                    
                    //replace data content sknya lepar juga tgl_berlaku_sk
                    $replace_sk = Model::replaceBA($ba,$data_esk_master['id'],$new_effective_date,$content_sk,$salary,$data_esk_master['decree_title'],$data_esk_master['authority'],$periode,$nodin,$manual_content_1,$manual_content_2,$manual_content_3,$keterangan_ba_1,$keterangan_ba_2,$keterangan_ba_3,$keputusan_direksi_1,$keputusan_direksi_2,$keputusan_direksi_3,$nama_penyakit,$nominal_insentif);

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
                    $model = new EskLists();
                    $model->attributes = $data_ba->attributes;
                    $model->id_ba_detail = $ba;
                    $model->nomor_ba = $data_ba->beritaAcaras->no;
                    $model->ba_date = $data_ba->beritaAcaras->ba_date;
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
                    $model->gaji_dasar = $salary['gaji_dasar'];
                    $model->tunjangan_biaya_hidup = $salary['tunjangan_biaya_hidup'];
                    $model->tunjangan_jabatan = $salary['tunjangan_jabatan'];
                    $model->tunjangan_fungsional = $salary['tunjangan_fungsional'];
                    $model->tunjangan_rekomposisi = $salary['tunjangan_rekomposisi'];
                    $model->level_tbh = $data_ba->new_bi;
                    $model->level_tr = $data_ba->new_bi;
                    $model->decree_nama = $data_esk_master['decree_nama'];
                    $model->decree_nik = $data_esk_master['decree_nik'];
                    $model->decree_title = $data_esk_master['decree_title'];
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
					
					if($model->new_directorate == 'Sales Directorate' || $model->new_directorate == 'Marketing Directorate') {
						$model->atasan_created = '76025';
					} elseif ($model->new_directorate == "CEO's Office Directorate" || $model->new_directorate == 'Human Capital Management Directorate') {
						$model->atasan_created = '78225';
					} elseif ($model->new_directorate == 'Finance Directorate') {
						$model->atasan_created = '76264';
					} elseif ($model->new_directorate == 'Information Technology Directorate') {
						$model->atasan_created = '82023';
					} elseif ($model->new_directorate == 'Network Directorate') {
						$model->atasan_created = '75066';
					} elseif ($model->new_directorate == 'Planning and Transformation Directorate') {
						$model->atasan_created = '73092';
					}
					
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
                    //var_dump($model->new_directorate);exit;
                    //get id esk master
                    //$id_approval = EskApprovalMaster::find()->where(['band' => $databp[0], 'authority_area' => $data_esk_master['authority']])->andWhere('directorate like "%'.$model->old_directorate.'%"')->one();
					$id_approval = EskApprovalMaster::find()->where(['band' => $databp[0]])->andWhere('authority_area like "%'.$data_esk_master['authority'].'%"')->andWhere('directorate like "%'.$model->old_directorate.'%"')->one();
					//var_dump($id_approval);exit;
					if(!empty($id_approval)){
                        $model->id_approval = $id_approval->id;
                        if($model->save()){
                            //set success count
                            $countSuccess = $countSuccess + 1;

                            //update berita acara detail flag
                            $ba_detail = GenerateEsk::findOne($ba);
                            $ba_detail->flag_esk = 1;
                            $ba_detail->save();

                            //=== save approval esk start ===// 
                            $data_approval = EskApprovalDetail::find()->where(['id_approval_master' => $id_approval->id])->all();
                            foreach($data_approval as $approval){
                                $data_a = new EskApprovalLists();
                                $data_a->id_esk = $model->id;
                                $data_a->approval_nik = $approval->nik;
                                $data_a->approval_name = $approval->employee->nama;
                                $data_a->approval_mail = $approval->employee->email;
                                $data_a->approval_title = $approval->employee->title;
                                $data_a->sequence = $approval->sequence;
                                $data_a->save();
                            }

                            //=== save ack esk start ===//
                            $this->setAcknowlegeLists($data_esk_master['flag_deliver_to'], $model->nik, $model->old_position, $model->new_position, $model->new_position_id, $data_esk_master['authority'], $model->id, $model->new_directorate);
                            //=== save ack esk end ===//

                            //save workflow esk action "Pembuatan eSK oleh Drafter (nama karyawan login)"
                            Model::setWorkFlow($model->id,"Pembuatan eSK oleh Drafter","-");

                            //logging data
                            Model::saveLog(Yii::$app->user->identity->username, "Generate eSK with ID ".$model->id);
                        }else{
                            //set failed count
                            $countFailed = $countFailed + 1;

                            //logging data
                            $error = implode(",",$model->getErrorSummary(true));
                            array_push($failed_array,"data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because ".$error);
                            Model::saveLog(Yii::$app->user->identity->username, "Failed generate eSK for Berita Acara ID ".$ba." because ".$error);
                        }
                    }else{
                        //set failed count
                        $countFailed = $countFailed + 1;

                        //logging data
                        array_push($failed_array,"data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because approval data that matches type of esk not found");
                        Model::saveLog(Yii::$app->user->identity->username, "Failed generate eSK for Berita Acara ID ".$ba." because approval data not found!");
                    }
                }else{
                    //set failed count
                    $countFailed = $countFailed + 1;

                    //logging data
                    array_push($failed_array,"data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because template that matches type of esk not found");
                    Model::saveLog(Yii::$app->user->identity->username, "Failed generate eSK for Berita Acara ID ".$ba." because template of eSK not found!");
                }

                //count iteration
                $countAll = $countAll + 1;
           }

            if(!empty($failed_array)){
                $failed_data = "that is ".implode(", ",array_unique($failed_array));
            }else{
                $failed_data = "";
            }

            //process send mail jika ada yang success 
            if($countSuccess > 0){
                $dataBatch = EskLists::find()->where(['id_batch' => $batch_process])->orderBy('id ASC')->all();
                $emailArray = array();
                $authority_array = array();
                $toMailArray = array();

                foreach($dataBatch as $esk){
                    array_push($emailArray, 
                        "
                        <tr>
                            <td width='20%'>" . $esk->number_esk . "</td>
                            <td width='15%'>" . $esk->nik . "</td>
                            <td width='25%'>" . $esk->nama . "</td>
                            <td width='20%'>" . ucwords($esk->about_esk) . "</td>
                            <td width='20%'>" . Model::TanggalIndo($esk->effective_esk_date) . "</td>
                        </tr>
                        "
                    );
                    array_push($authority_array,'"'.$esk->authority.'"');

                    //push ke array head untuk email headnya
                    if(!in_array(Model::getMailHeadCreated($esk->head_created), $toMailArray)){
                        array_push($toMailArray, Model::getMailHeadCreated($esk->head_created));
                    }

                    //push ke array creator
                    if(!empty(Model::getMailCreated($esk->created_by))){
                        if(!in_array(Model::getMailCreated($esk->created_by), $toMailArray)){
                            array_push($toMailArray, Model::getMailCreated($esk->created_by));
                        }
                    }
                }

                //send mail 
                $subject = "[eSK] Request of Processing eSK with Batch ID ".$batch_process."";
                $to = $toMailArray;
                $content = $this->renderPartial('../../mail/mail-process',['esk' => $emailArray],true);
                Model::sendMailMultiple($to,$subject,$content);
            }
            
            //send flash message berisi count success, count failed dan count all
            Yii::$app->session->setFlash('info', 'Successfully generated ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 
            if($countAll == $countSuccess){
                return $this->redirect(['staffing-lists/generated']);
            }else{
                return $this->redirect(['generate-esk/index']);
            }
        }
        
        $model->id_ba_detail  = $id;
        $searchModel = new GenerateEskSearch();
        $array_id = explode(",",$id);
        $details = $searchModel->findDetails($array_id);
        $count_proposed = $searchModel->countDetails($array_id);

        return $this->render('generate', [
            'model' => $model,
            'id_ba' => $id,
            'details' => $details,
            'flag_periode' => 0,
            'flag_nodin' => 0,
            'flag_nama_penyakit' => 0,
            'flag_nominal_insentif' => 0,
            'flag_content_1' => 0,
            'flag_content_2' => 0,
            'flag_content_3' => 0,
            'flag_ba_1' => 0,
            'flag_ba_2' => 0,
            'flag_ba_3' => 0,
            'flag_kd_1' => 0,
            'flag_kd_2' => 0,
            'flag_kd_3' => 0,
            'count_proposed' => $count_proposed
        ]);
    }

    public function actionDelete($id){
        $ba_detail = GenerateEsk::findOne($id);
        $ba_detail->flag_esk = 2;
        
        if ($ba_detail->save()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete Berita Acara Data with ID BA ".$id);
          
            Yii::$app->session->setFlash('success', "Your Berita Acara (BA) data successfully canceled and waiting for confirmation."); 
        } else {
            Yii::$app->session->setFlash('error', "Your Berita Acara (BA) was not canceled.");
        }

        return $this->redirect(['index']);
    }

    public function actionApproval()
    {   
        if (Yii::$app->request->post()) {
           $request = Yii::$app->request->post();

           //inisialisasi data count 
            $countSuccess = 0;
            $countFailed = 0;
            $countAll = 0;
            $failed_array = array();

           //get data generate
           $id_ba_cancel = explode(",",$request['id_ba_cancel']);
           foreach($id_ba_cancel as $id_ba){
                $ba_detail = GenerateEsk::findOne($id_ba);
                $ba_detail->flag_esk = -1;
                if($ba_detail->save()) {
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Delete Berita Acara Data with ID BA ".$id_ba);

                    $countSuccess++;
                } else {
                    $countFailed = $countFailed + 1;

                    //logging data
                    $error = implode(",",$model->getErrorSummary(true));
                    array_push($failed_array,"data BA ".$data_ba->nik."/".$data_ba->nama."/".$data_ba->tipe." failed because ".$error);
                }

                //count iteration
                $countAll = $countAll + 1;
            }

            if(!empty($failed_array)){
                $failed_data = "that is ".implode(", ",array_unique($failed_array));
            }else{
                $failed_data = "";
            }

            //send flash message berisi count success, count failed dan count all
            Yii::$app->session->setFlash('info', 'Successfully canceled ' . $countAll . ' BA data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 
            return $this->redirect(['generate-esk/approval/']);
        }

        $searchModel = new GenerateEskSearch();
        $dataProvider = $searchModel->approvalCancelLists();

        return $this->render('approval', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCancelLists()
    {   
        $searchModel = new GenerateEskSearch();
        $dataProvider = $searchModel->cancelLists();

        return $this->render('cancel', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionUpdate($id,$id_all_ba)
    {
        $model = GenerateEsk::findOne($id);

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();

            //explode data old kota dan new kota
            $data_old_kota = explode(":",$request['GenerateEsk']['old_kota']);
            $data_new_kota = explode(":",$request['GenerateEsk']['new_kota']);

            //get data generate
            $old_bp = $request['GenerateEsk']['old_bp'];
            $new_bp = $request['GenerateEsk']['new_bp'];
            $old_bi = $request['GenerateEsk']['old_bi'];
            $new_bi = $request['GenerateEsk']['new_bi'];
            $old_kode_kota = $data_old_kota[0];
            $new_kode_kota = $data_new_kota[0];
            $old_kota = $data_old_kota[1];
            $new_kota = $data_new_kota[1];
            $old_area = $request['GenerateEsk']['old_area'];
            $new_area = $request['GenerateEsk']['new_area'];

            //optional content
            $nota_dinas = $request['GenerateEsk']['nota_dinas'];
            $periode = $request['GenerateEsk']['periode'];
            $nama_penyakit = $request['GenerateEsk']['nama_penyakit'];
            $nominal_insentif = str_replace(",","",$request['GenerateEsk']['nominal_insentif']);
            $kd_1 = $request['GenerateEsk']['keputusan_direksi_1'];
            $ba_1 = $request['GenerateEsk']['keterangan_ba_1'];
            $kd_2 = $request['GenerateEsk']['keputusan_direksi_2'];
            $ba_2 = $request['GenerateEsk']['keterangan_ba_2'];
            $kd_3 = $request['GenerateEsk']['keputusan_direksi_3'];
            $ba_3 = $request['GenerateEsk']['keterangan_ba_3'];
            $scholar_program = $request['GenerateEsk']['scholarship_program'];
            $scholar_university = $request['GenerateEsk']['scholarship_university'];
            $scholar_level = $request['GenerateEsk']['scholarship_level'];
            $cltp_reason = $request['GenerateEsk']['cltp_reason'];
            $start_sick = empty($request['GenerateEsk']['start_date_sick']) ? "" : date("Y-m-d",strtotime($request['GenerateEsk']['start_date_sick']));
            $end_sick = empty($request['GenerateEsk']['end_date_sick']) ? "" : date("Y-m-d",strtotime($request['GenerateEsk']['end_date_sick']));
            $phk_date = empty($request['GenerateEsk']['phk_date']) ? "" : date("Y-m-d",strtotime($request['GenerateEsk']['phk_date']));
            $statement_date = empty($request['GenerateEsk']['tanggal_td_pernyataan']) ? "" : date("Y-m-d",strtotime($request['GenerateEsk']['tanggal_td_pernyataan']));
            $last_payroll_date = empty($request['GenerateEsk']['last_payroll']) ? "" : date("Y-m-d",strtotime($request['GenerateEsk']['last_payroll']));
            $resign_date = empty($request['GenerateEsk']['resign_date']) ? "" : date("Y-m-d",strtotime($request['GenerateEsk']['resign_date']));
            $flag_kisel = $request['GenerateEsk']['flag_kisel'];

            $model->tipe = $request['GenerateEsk']['tipe'];
            $model->old_kode_kota = $old_kode_kota;
            $model->new_kode_kota = $new_kode_kota;
            $model->old_kota = $old_kota;
            $model->new_kota = $new_kota;
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

            if($model->save()){
                //update lagi datanya untuk title
                $model2 = GenerateEsk::findOne($id);
                $model2->old_title = $model->positionOld->nama;
                $model2->new_title = $model->positionNew->nama;
                $model2->save();

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update BA Lists with ID ".$model->id);
                Yii::$app->session->setFlash('success', "BA data successfully updated!");
            }else{
                //logging data
                $error = implode(",",$model->getErrorSummary(true));
                Model::saveLog(Yii::$app->user->identity->username, "Failed update BA data for ID ".$model->id." because ".$error);
                Yii::$app->session->setFlash('error', "Failed update, because ".$error);
            }

            //return to generate page
            $generated = new EskLists();
            $generated->id_ba_detail  = $id_all_ba;
            $searchModel = new GenerateEskSearch();
            $array_id = explode(",",$id_all_ba);
            $details = $searchModel->findDetails($array_id);
            $count_proposed = $searchModel->countDetails($array_id);

            return $this->render('generate', [
                'model' => $generated,
                'id_ba' => $id_all_ba,
                'details' => $details,
                'flag_periode' => 0,
                'flag_nodin' => 0,
                'flag_nama_penyakit' => 0,
                'flag_nominal_insentif' => 0,
                'flag_content_1' => 0,
                'flag_content_2' => 0,
                'flag_content_3' => 0,
                'flag_ba_1' => 0,
                'flag_ba_2' => 0,
                'flag_ba_3' => 0,
                'flag_kd_1' => 0,
                'flag_kd_2' => 0,
                'flag_kd_3' => 0,
                'count_proposed' => $count_proposed
            ]);
        }

        //check if kode kota empty
        $old_kode_kota = City::find()->where(['name' => $model->old_kota])->one();
        $new_kode_kota = City::find()->where(['name' => $model->new_kota])->one();

        $model->old_kota = strtoupper($model->old_kota);
        $model->old_kode_kota = !empty($model->old_kode_kota) ? $model->old_kode_kota : (empty($old_kode_kota) ? '' : $old_kode_kota->code);
        $model->new_kode_kota = !empty($model->new_kode_kota) ? $model->new_kode_kota : (empty($new_kode_kota) ? '' : $new_kode_kota->code);
        $model->new_kota = strtoupper($model->new_kota);

        return $this->renderAjax('_edit', [
            'model' => $model,
        ]);
    }

    public function actionGetPosition($position_id){
        $data_posisi = Position::findOne($position_id);
        if(!empty($data_posisi)){
            $data = array(
                "result" => 1,
                "remark" => "Success",
                "organization" => $data_posisi->organization,
                "department" => $data_posisi->department,
                "division" => $data_posisi->division,
                "section" => $data_posisi->section,
                "bgroup" => $data_posisi->grp,
                "egroup" => $data_posisi->egrp,
                "directorate" => $data_posisi->directorate,
                "area" => $data_posisi->area,
                "kode_kota" => $data_posisi->city,
                "kota" => $data_posisi->desc_city,
                "bp" => $data_posisi->bp,
            );
        }else{  
            $data = array(
                "result" => 0,
                "remark" => "Failed get data, position data is empty!",
                "organization" => "",
                "department" => "",
                "division" => "",
                "section" => "",
                "bgroup" => "",
                "egroup" => "",
                "directorate" => "",
                "area" => "",
                "kode_kota" => "",
                "kota" => "",
                "bp" => ""
            );
        }

        return json_encode($data);
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
     * Finds the GenerateEsk model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return GenerateEsk the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = GenerateEsk::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionPositionoldlists($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(['DISTINCT(position.id) AS id', 'position.nama AS text'])
                ->from('berita_acara_detail')
                ->join('INNER JOIN','position','berita_acara_detail.old_position_id = position.id')
                ->where(['like', 'position.nama', $q]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' =>  Position::find($id)->nama];
        }
        return $out;
    }

    public function actionPositionnewlists($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(['DISTINCT(position.id) AS id', 'position.nama AS text'])
                ->from('berita_acara_detail')
                ->join('INNER JOIN','position','berita_acara_detail.new_position_id = position.id')
                ->where(['like', 'position.nama', $q]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Position::find($id)->nama];
        }
        return $out;
    }
}
