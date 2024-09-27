<?php

namespace esk\controllers;

use Yii;
use esk\models\EskLists;
use esk\models\StaffingLists;
use esk\models\EskListsSearch;
use esk\models\EskApprovalLists;
use esk\models\EskApprovalDetail;
use esk\models\EskAcknowledgeLists;
use esk\models\EskWorkflowLists;
use esk\models\EskTemplateMaster;
use esk\models\EskFlagData;
use esk\models\Model;
use esk\models\Employee;
use esk\models\GenerateEsk;
use common\models\City;
use esk\components\Helper;
use esk\models\Position;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\db\mssql\PDO;

/**
 * StaffingListsController implements the CRUD actions for EskLists model.
 */
class StaffingListsController extends Controller
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
                        'actions' => ['login', 'error','preview','index','detail','modaldelivered','deliveredall'],
                        'allow' => true,
                    ],
                    [
                        'allow' => true,
                        'roles' => ['sysadmin','hc_staffing','hcbp_account'],
                    ],
                    [
                        'actions' => ['index','generated'],
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            if(Model::countStaffingProcessedTransformation() <= 0){
                                $can_deliver = false;
                            }else{
                                $can_deliver = true;
                            }

                            if($can_deliver){
                                return $can_deliver;
                            }

                            //check if role
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
     * Lists all EskLists models.
     * @return mixed
     */
    public function actionGenerated()
    {   
        $flag_all = yii::$app->request->get('flag_all');

        $searchModel = new EskListsSearch();
        if(Yii::$app->user->can('sysadmin') || $flag_all == "1"){
            //tampilkan semuanya
            $dataProviderGenerated = $searchModel->findEskGenerated();
            $flag_all = 1;
        }else{
            //get area user
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $user_area = Yii::$app->user->identity->employee->area;
            } else{
                $user_area = "N/A";
            }

            $dataProviderGenerated = $searchModel->findEskGeneratedFilter($user_area);
        }
        
        return $this->render('generated', [
            'searchModel' => $searchModel,
            'dataProviderGenerated' => $dataProviderGenerated,
            'flag_all' => $flag_all,
        ]);
    }

    public function actionIndex()
    {   
        $flag_all = yii::$app->request->get('flag_all');

        $searchModel = new EskListsSearch();
        
		if(
            (!empty(yii::$app->request->get('tipe')) || !empty(yii::$app->request->get('nik'))) 
            || !empty(yii::$app->request->get('nama')) || !empty(yii::$app->request->get('start_date')) 
            || !empty(yii::$app->request->get('groupemp')) || !empty(yii::$app->request->get('about'))
			|| !empty(yii::$app->request->get('number_esk'))
        ){
			$tipe = yii::$app->request->get('tipe');
			$nik = yii::$app->request->get('nik');
			$nama = yii::$app->request->get('nama');
			$start_date = yii::$app->request->get('start_date');
			$groupemp = yii::$app->request->get('groupemp');
			$about = yii::$app->request->get('about');
			$numberesk = yii::$app->request->get('number_esk');
			
			$queryDate = (empty($start_date_data) && empty($end_date_data)) ? '' : "and (effective_esk_date between '".$start_date."' and '".$start_date."')";
            $queryID = (empty($tipe)) ? '' : "and esk_lists.tipe like '%".$tipe."%'";
            $queryNik = (empty($nik)) ? '' : "and esk_lists.nik like '%".$nik."%'";
            $queryNama = (empty($nama)) ? '' : "and esk_lists.nama like '%".$nama."%'";
			$queryGroup = (empty($groupemp)) ? '' : "and employee.bgroup like '%".$groupemp."%'";
			$queryAbout = (empty($about)) ? '' : "and esk_lists.about_esk like '%".$about."%'";
			$queryNumber = (empty($numberesk)) ? '' : "and esk_lists.number_esk like '%".$numberesk."%'";
			
			$query = $queryDate." ".$queryID." ".$queryGroup." ".$queryNik." ".$queryNama." ".$queryAbout." ".$queryNumber;
			
			//var_dump($query);exit;
		}

		if(Yii::$app->user->can('sysadmin')){
            //tampilkan semuanya
            $dataProviderProcessed = $searchModel->findEskProcessed(Yii::$app->request->queryParams);
            //$dataProviderProcessed = $searchModel->search(Yii::$app->request->queryParams);
			//$flag_all = 1;
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $user_title = Yii::$app->user->identity->employee->job_category;
            } else{
                $user_title = "N/A";
            }
        }else{
            //get area user
		
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $user_area = Yii::$app->user->identity->employee->area;
                $user_title = Yii::$app->user->identity->employee->job_category;
            } else{
                $user_area = "N/A";
                $user_title = "N/A";
            }
            $directorate_group = Model::getDirectorateDeliver();
            //$dataProviderProcessed = $searchModel->findEskProcessedFilter($user_area,$directorate_group);
            $dataProviderProcessed = $searchModel->findEskProcessedFilterUpdate($user_area,$directorate_group, $query);
        }
		
		$dataProviderProcessed->pagination = ['pageSize' => 50];
		
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProviderProcessed' => $dataProviderProcessed,
            'flag_all' => $flag_all,
            'user_title' => $user_title,
			'start_date' => $start_date,
            'tipe' => $tipe,
            'nik' => $nik,
            'nama' => $nama,
            'groupemp' => $groupemp,
            'about' => $about,
			'number_esk' => $numberesk,
        ]);
    }

    /**
     * Displays a single EskLists model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id_batch)
    {   
        $searchModel = new EskListsSearch();
        $data = $searchModel->getEskGenerated($id_batch);
        $data_esk = EskLists::find()->where(['id_batch' => $id_batch])->one();

        return $this->render('view', [
            'id_batch' => $id_batch,
            'data_esk' => $data_esk,
            'dataProvider' => $data,
        ]);
    }

    public function actionDetail($id){
        $model = EskLists::find()->where(['id' => $id])->one();
        $approval = EskApprovalLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->all();
        $count_app = EskApprovalLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->all();
        $workflow = EskWorkflowLists::find()->where(['id_esk' => $id])->orderBy('created_at ASC')->all();
        $maxApp = EskApprovalLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $id])->one();
        $count_pending = EskApprovalLists::find()->where(['id_esk' => $id,'status' => 'pending'])->count();
        $ack = EskAcknowledgeLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->all();
        $count_ack = EskAcknowledgeLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->count();
        $last_ack = EskAcknowledgeLists::find()->select('MAX(sequence) as sequence')->where(['id_esk' => $id])->one();

        //get title login user
        if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
            $user_title = Yii::$app->user->identity->employee->job_category;
        } else{
            $user_title = "N/A";
        }

        return $this->render('detail', [
            'model' => $model,
            'approval' => $approval,
            'count_app' => $count_app,
            'acknowledge_data' => $ack,
            'count_ack' => $count_ack,
            "workflow" => $workflow,
            'maxApp' => $maxApp,
            'count_pending' => $count_pending,
            'user_title' => $user_title,
            "last_ack" => $last_ack->sequence
        ]);
    }

    public function actionPreview($id)
    {   
        $model = $this->findModel($id);
        //check status esk 
        /*if($model->status == "published" || $model->status == "approved"){
            $file_name = $model->file_name;
        }else{
            $file_name = "";
        }*/
        $file_name = "";
        $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"preview");

        return $this->renderAjax('preview', [
            'content' => $all_content,
        ]);
    }

    /**
     * Updates an existing EskLists model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id,$flag)
    {
        $model = $this->findModel($id);
        $old_esk = $model->no_esk;
        $id_approval_old = $model->id_approval;

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();
			
			if($model->old_directorate == "") {
				Yii::$app->session->setFlash('error', "Failed update, Old Directorate is Empty (Directorate Lama Tidak Boleh Kosong).");
				return $this->redirect(['view', 'id_batch'=>$model->id_batch]);
			} elseif($model->new_directorate == "") {
				Yii::$app->session->setFlash('error', "Failed update, New Directorate is Empty (Directorate Baru Tidak Boleh Kosong).");
				return $this->redirect(['view', 'id_batch'=>$model->id_batch]);
			} elseif($model->old_area == "") {
				Yii::$app->session->setFlash('error', "Failed update, Old Area is Empty (Area Lama Tidak Boleh Kosong).");
				return $this->redirect(['view', 'id_batch'=>$model->id_batch]);
			} elseif($model->new_area == "") {
				Yii::$app->session->setFlash('error', "Failed update, New Area is Empty (Area Baru Tidak Boleh Kosong).");
				return $this->redirect(['view', 'id_batch'=>$model->id_batch]);
			} 
			
            //explode data old kota dan new kota
            $data_old_kota = explode(":",$request['StaffingLists']['old_kota']);
            // sprint 4
            // $data_new_kota = explode(":",$request['StaffingLists']['new_kota']);
            $data_new_kota = $request['StaffingLists']['new_kota'];
            $data_new_kode_kota = $request['StaffingLists']['new_kode_kota'];
            // end

            //get data generate
            $tgl_berlaku = date("Y-m-d",strtotime($request['StaffingLists']['effective_esk_date']));
            $old_bp = $request['StaffingLists']['old_bp'];
            $new_bp = $request['StaffingLists']['new_bp'];
            $old_bi = $request['StaffingLists']['old_bi'];
            $new_bi = $request['StaffingLists']['new_bi'];
			$level_band = $request['StaffingLists']['level_band'];
			$level_gaji = $request['StaffingLists']['level_gaji'];
			$band = $request['StaffingLists']['band'];
            $old_kode_kota = $data_old_kota[0];
            $new_kode_kota = $data_new_kode_kota;
            $old_kota = $data_old_kota[1];
            $new_kota = $data_new_kota;
            $old_area = $request['StaffingLists']['old_area'];
            $new_area = $request['StaffingLists']['new_area'];
            $gd = str_replace(",","",$request['StaffingLists']['gaji_dasar']); //gaji dasar;
            $tbh = str_replace(",","",$request['StaffingLists']['tunjangan_biaya_hidup']); //tunjangan biaya hidup
            $tj = str_replace(",","",$request['StaffingLists']['tunjangan_jabatan']); //tunjangan jabatan
            $tf = str_replace(",","",$request['StaffingLists']['tunjangan_fungsional']); //tunjangan fungsional
            $tr = str_replace(",","",$request['StaffingLists']['tunjangan_rekomposisi']); //tunjangan rekomposisi
            $id_kd_org = $request['kd_organisasi'];
			
			$gaji_nss = $request['StaffingLists']['gaji_dasar_nss'];
			$tunjab_nss = $request['StaffingLists']['tunjab_nss'];
			$rekom_nss = $request['StaffingLists']['tunjangan_rekomposisi_nss'];
			$tbs_nss = $request['StaffingLists']['tbh_nss'];
            
			//optional content
            $nota_dinas = $request['StaffingLists']['nota_dinas'];
            $periode = $request['StaffingLists']['periode'];
            $nama_penyakit = $request['StaffingLists']['nama_penyakit'];
            $nominal_insentif = str_replace(",","",$request['StaffingLists']['nominal_insentif']);
            $kd_1 = $request['StaffingLists']['keputusan_direksi_1'];
            $ba_1 = $request['StaffingLists']['keterangan_ba_1'];
            $kd_2 = $request['StaffingLists']['keputusan_direksi_2'];
            $ba_2 = $request['StaffingLists']['keterangan_ba_2'];
            $kd_3 = $request['StaffingLists']['keputusan_direksi_3'];
            $ba_3 = $request['StaffingLists']['keterangan_ba_3'];
            $scholar_program = $request['StaffingLists']['scholarship_program'];
            $scholar_university = $request['StaffingLists']['scholarship_university'];
            $scholar_level = $request['StaffingLists']['scholarship_level'];
            $cltp_reason = $request['StaffingLists']['cltp_reason'];
            $start_sick = empty($request['StaffingLists']['start_date_sick']) ? "" : date("Y-m-d",strtotime($request['StaffingLists']['start_date_sick']));
            $end_sick = empty($request['StaffingLists']['end_date_sick']) ? "" : date("Y-m-d",strtotime($request['StaffingLists']['end_date_sick']));
            $phk_date = empty($request['StaffingLists']['phk_date']) ? "" : date("Y-m-d",strtotime($request['StaffingLists']['phk_date']));
            $statement_date = empty($request['StaffingLists']['tanggal_td_pernyataan']) ? "" : date("Y-m-d",strtotime($request['StaffingLists']['tanggal_td_pernyataan']));
            $last_payroll_date = empty($request['StaffingLists']['last_payroll']) ? "" : date("Y-m-d",strtotime($request['StaffingLists']['last_payroll']));
            $resign_date = empty($request['StaffingLists']['resign_date']) ? "" : date("Y-m-d",strtotime($request['StaffingLists']['resign_date']));
            $flag_kisel = $request['StaffingLists']['flag_kisel'];

            //phk content
            $flag_gaji = $request['flag_gaji'];
            $flag_uang_pisah = $request['flag_uang_pisah'];
            $flag_ganti_rumah = $request['flag_ganti_rumah'];
            $flag_ganti_cuti = $request['flag_ganti_cuti'];
            $flag_homebase = $request['flag_homebase'];
            $flag_insentif = $request['flag_insentif'];
            $flag_ket_kerja = $request['flag_ket_kerja'];
            $uang_pisah_value = $request['uang_pisah_value'];
			
            //get data mutasi, eva dan dpe 
			//$dataMED = Model::getMutasiEvaDpe($old_kota, $new_kota, "", "", $model->tipe, $model->dpe_ba);
            $dataMED = Model::getMutasiEvaDpe($old_kota, $new_kota, $old_kode_kota, $new_kode_kota, $model->tipe, $model->dpe_ba);

            //explode data BP 
            $databp = explode(".",$new_bp);                
			
			$dataTemplateMaster = EskTemplateMaster::find()->where(['id' => $model->id_template_master])->one();
			$model->code_template = $dataTemplateMaster->code_template;
			$model->tipe = $dataTemplateMaster->type;
			$model->about_esk = $dataTemplateMaster->about;
			
            //search tipe ba di template esk master
			$dataMasterEsk = EskTemplateMaster::findOne(['type' => $model->tipe]);
			$model->code_template = $dataMasterEsk->code_template;
            $data_esk_master = Model::checkTemplateMaster($model->tipe,$model->code_template,$databp[0],$model->old_area,$model->new_area,$model->old_directorate);
			//var_dump($model->tipe,$dataMasterEsk->code_template,$model->code_template,$databp[0],$model->old_area,$model->new_area,$model->old_directorate);exit;
            if(!empty($data_esk_master)){
                //ada templatenya save id 
                if($data_esk_master['number_esk'] !== $old_esk || $request['StaffingLists']['no_esk'] !== $old_esk){
                    if($request['StaffingLists']['no_esk'] !== $old_esk){
                        $no_esk_update  = $data_esk_master['number_esk']; //$request['StaffingLists']['no_esk'];
                        $sequence_sk = $model->sequence;
                        $month = $model->month_id;
                        $year = $model->year_id;
                        $first_number = Model::getFirstNumber($sequence_sk);
                        $esk_no_full = $first_number . "" . $no_esk_update . "" . Model::getMonthYear($month, $year); 

                        $model->no_esk = $no_esk_update;
                        $model->number_esk = $esk_no_full;
                    }else{
                        $no_esk_update = $data_esk_master['number_esk'];
                        $month = date("m");
                        $year = date("Y");
                        $sequence_sk = $model->sequence; //Model::getSequenceEsk($no_esk_update,$year);
                        $first_number = Model::getFirstNumber($sequence_sk);
                        $esk_no_full = $first_number . "" . $no_esk_update . "" . Model::getMonthYear($month, $year); 
                                
                        $model->sequence = $sequence_sk;
                        $model->month_id = $month;
                        $model->year_id = $year;
                        $model->number_esk = $esk_no_full;
                        $model->no_esk = $no_esk_update;
                    }
                }
                $model->about_esk = $data_esk_master['about'];
                $model->effective_esk_date = $tgl_berlaku;
                $model->id_template_master = $data_esk_master['id'];
                $model->authority = $data_esk_master['authority'];
                $model->gaji_dasar = (int) $gd;
                $model->tunjangan_biaya_hidup = (int) $tbh;
                $model->tunjangan_jabatan = (int) $tj;
                $model->tunjangan_fungsional = (int) $tf;
                $model->tunjangan_rekomposisi = (int) $tr;
                $model->structural = ($request['StaffingLists']['structural'] == 1) ? "Y" : null;
                $model->functional = ($request['StaffingLists']['functional'] == 1) ? "Y" : null;
                $type_before_update = $model->tipe;
                $model->tipe = $request['StaffingLists']['tipe'];
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
                $model->notif_stat_date = $statement_date; // sprint 4 faqih
                $model->last_payroll = $last_payroll_date;
                $model->resign_date = $resign_date;
                $model->flag_kisel = $flag_kisel;
				$model->level_band = $level_band;
				$model->level_gaji = $level_gaji;
				$model->band = $band;
				$model->gaji_dasar_nss = $gaji_nss;
				$model->tunjab_nss = $tunjab_nss;
				$model->tunjangan_rekomposisi_nss = $rekom_nss;
				$model->tbh_nss = $tbs_nss;

                // add by faqih sprint 4
                // $model->sync_status = 0;
                $model->updated_at   = date("Y-m-d H:i:s");
                $model->flag_update   = 1;

                $position_baru = $request['StaffingLists']['new_position'];
                if ($request['StaffingLists']['structural'] == '1' && $level_band > $level_gaji) {
                    $position_baru = 'Pj. ' . $request['StaffingLists']['new_position'];
                }
                $model->new_title = $position_baru;
                // add by faqih UT sprint 4
                $model->grade = trim(ucwords($request['StaffingLists']['grade']));

                $model->new_position_id = $request['StaffingLists']['new_position_id'];

                $model->dpe_length = $request['StaffingLists']['dpe_length'];
                $model->dpe_unit = $request['StaffingLists']['dpe_unit'];
                $model->nik_new_atasan = $request['StaffingLists']['nik_new_atasan'];

                // end
                
                if(!empty($request['StaffingLists']['vp_nik'])){
                    $model->vp_nik = $request['StaffingLists']['vp_nik'];
                }
                if(!empty($request['StaffingLists']['decree_nik'])){
                    $emp_data = Employee::find()->where(['nik' => $request['StaffingLists']['decree_nik']])->one();
                    if(!empty($emp_data)){
                        $model->decree_nik = $emp_data->nik;
                        $model->decree_nama = $emp_data->nama;
                        $model->decree_title = $emp_data->title;
                    }
                }
				
				// cek data grade di ebs by faqih frs

                $sql = "SELECT * FROM TSEL_HR_GRADE_V WHERE grade = '".$model->grade."'";
                $conn = Yii::$app->dbOra;
                $commandOra = $conn->createCommand($sql)
                ->queryOne();
                if(empty($commandOra)){
                    Yii::$app->session->setFlash('error', "Failed update, Grade data is not in EBS.");
                    exit;
                }else{
                    if($model->save())
                    {
                        //update approval data
                        if(!empty($request['StaffingLists']['id_approval']) && $id_approval_old != $request['StaffingLists']['id_approval'] && ($model->status != "delivered" || $model->status != "approved" || $model->status != "published")){
                            $deleteAllApproval = EskApprovalLists::deleteAll(['id_esk' => $model->id]);
                            $data_approval = EskApprovalDetail::find()->where(['id_approval_master' => $request['StaffingLists']['id_approval']])->all();
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
                        }

                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Update eSK Lists with ID ".$model->id);
                        Yii::$app->session->setFlash('success', "eSK data successfully updated!");

                        //regenerate ulang jika tipe berbeda dengan sebelumnya atau tipe adalah resign atau perubahan kd organisasi
                        if(($model->tipe !== $type_before_update) || strpos($model->tipe,"Resign") !== false || !empty($id_kd_org)){
                            $esk2 = EskLists::findOne($id); 
                            $esk2->content_esk = Model::regenerateEsk($id,$flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja, $uang_pisah_value,$id_kd_org);
                            $esk2->save();
                        }
                    }else{
                        //logging data
                        $error = implode(",",$model->getErrorSummary(true));
                        Model::saveLog(Yii::$app->user->identity->username, "Failed update eSK data for ID ".$model->id." because ".$error);
                        Yii::$app->session->setFlash('error', "Failed update, because ".$error);
                    }
                }
            }else{
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Failed update eSK data because template that matches data of esk not found!");
                Yii::$app->session->setFlash('error', "Failed update, template that matches data of esk not found.");
            }   

            //pilihan apakah menu generate or process
            if($flag == "generated"){
                return $this->redirect(['view', 'id_batch'=>$model->id_batch]);
            }elseif($flag == "all-esk"){
                return $this->redirect(['esk-lists/preview', 'id'=>$model->id]);
            }else{
                return $this->redirect(['detail', 'id'=>$model->id]);
            }
            
        }

        //check if kode kota empty
        $old_kode_kota = City::find()->where(['name' => $model->old_kota])->one();
        $new_kode_kota = City::find()->where(['name' => $model->new_kota])->one();

        $model->old_kota = strtoupper($model->old_kota);
        $model->old_kode_kota = !empty($model->old_kode_kota) ? $model->old_kode_kota : (empty($old_kode_kota) ? '' : $old_kode_kota->code);
        $model->new_kode_kota = !empty($model->new_kode_kota) ? $model->new_kode_kota : (empty($new_kode_kota) ? '' : $new_kode_kota->code);
        $model->new_kota = strtoupper($model->new_kota);
        $model->structural = ($model->structural == "Y") ? 1 : 0;
        $model->functional = ($model->functional == "Y") ? 1 : 0;

        $model->new_position = str_replace("Pj. ","",$model->new_position);
        $model->new_position_id = $model->new_position_id;

        return $this->renderAjax('update', [
            'model' => $model,
        ]);
    }

    public function actionDetailApproval($id){
        $detail = EskApprovalDetail::find()->where(['id_approval_master' => $id])->all();
        $detail_array = array();
        $i = 1;
        foreach($detail as $detail){
            array_push($detail_array,
            '
                <tr>
                    <td align="center">'.$i.'</td>
                    <td align="center">'.$detail->nik.'</td>
                    <td>'.$detail->employee->nama.'</td>
                    <td>'.$detail->employee->title.'</td>
                    <td align="center">'.$detail->sequence.'</td>
                </tr>        
            '
            );
            $i++;
        }

        $content = '
            <table class="table table-hover">
                <tr>
                    <td width="3%" align="center"><b>#</b></td>
                    <td width="20%" align="center"><b>NIK</b></td>
                    <td width="30%"><b>Name</b></td>
                    <td><b>Title</b></td>
                    <td width="10%" align="center"><b>Sequence</b></td>
                </tr>
                '.implode("",$detail_array).'
            </table>
        ';

        return $content;
    }

    /**
     * Deletes an existing EskLists model.
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

    public function actionProcess(){
		set_time_limit(0);
		ini_set('memory_limit','912M');
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();

        $id_batch = yii::$app->request->get('id_batch');
        $id_esk = explode(",",yii::$app->request->get('id_esk'));
        $data_esk = EskLists::find()->where(['id_batch' => $id_batch,])->andWhere(['in','id',$id_esk])->andWhere(['status' => 'generated'])->all();
        foreach($data_esk as $esk){
            //get data approval 1
            $approval = EskApprovalLists::find()->where(['id_esk' => $esk->id])->one();
            $model = $this->findModel($esk->id);
            $model->flag_approval_seq = 1;
            $model->status = "processed";
            $model->tracking = "Awaiting approval of ".$approval->approval_title;
            if($model->save()){
                $countSuccess = $countSuccess + 1;

                //save workflow esk action "Pembuatan eSK oleh Drafter (nama karyawan login)"
                Model::setWorkFlow($model->id,"Review eSK data oleh HCBP Account/Area","-");

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Process eSK to approval with ID ".$model->id);

                //send mail ke approval pertama
                $subject = "[eSK] Request of Approval eSK Number ".$esk->number_esk."";
                $content = $this->renderPartial('../../mail/mail-approval',['esk' => $esk, 'approval' => $approval->approval_name, 'remark' => ''],true);
                Model::sendMailOne($approval->approval_mail,$subject,$content);
            }else{
                $countFailed = $countFailed + 1;
                $error = implode(",",$model->getErrorSummary(true));
                array_push($failed_array,$error);
                Model::saveLog(Yii::$app->user->identity->username, "Failed process eSK to approval with ID ".$model->id." because ".$error);
            }

            $countAll = $countAll + 1;
        }

        if(!empty($failed_array)){
            $failed_data = "because ".implode(", ",array_unique($failed_array));
        }else{
            $failed_data = "";
        }

        Yii::$app->session->setFlash('info', 'Successfully process ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 
        return $this->redirect(['staffing-lists/generated']);
    }

    public function actionDelivered(){
        $id_esk = yii::$app->request->get('id_esk');

        //kirim datanya ke fungsi workflow status
        $approval = EskApprovalLists::find()->where(['id_esk' => $id_esk])->one();
        $countPending = EskApprovalLists::find()->where(['id_esk' => $id_esk])->andWhere(['status' => 'pending'])->orWhere(['status' => 'rejected'])->count();
        $flag_data = EskFlagData::find()->one()->flag_ack;
        $count_ack = EskAcknowledgeLists::find()->where(['id_esk' => $id_esk])->count();

        if($count_ack > 0){
            $data_update = Model::WorkFlowStatus("delivered", $approval->id, $id_esk);
        }else{
            $this->published($id_esk);
            exit();
        }
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        //update jika masih ada yg pending/bypass by hc staffing adm
        if($countPending > 0){
            //update approved date 
            $data_esk->approved_esk_date = date("Y-m-d");
        }
        if($data_esk->save()){
            //update status approval yg pending jadi skipped 
            $data_app = EskApprovalLists::find()->where(['id_esk' => $id_esk,'status' => 'pending'])->all();
            if(!empty($data_app)){
                foreach ($data_app as $data_app) {
                    $data_app->status = "skipped by HCBP Account/Area";
                    $data_app->update(false);
                }
            }

            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            Model::setWorkFlow($data_esk->id,"HC Fungsi Staffing telah selesai memproses E-SK No.".$data_esk->number_esk,"-");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "HCBP Account/Area deliver eSK data with ID ".$data_esk->id);

            //send mail
            $subject = "[eSK] Delivered of eSK Number ".$data_esk->number_esk."";
            if($flag_data == 1){
                $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
                $content = $this->renderPartial('../../mail/mail-delivered',['esk' => $data_esk, 'head' => $data_ack->ack_name],true);        
                Model::sendMailOne($data_ack->ack_mail,$subject,$content);
            }else{
                $content = $this->renderPartial('../../mail/mail-delivered',['esk' => $data_esk, 'head' => $data_esk->vP->nama],true);        
                Model::sendMailOne($data_esk->vP->email,$subject,$content);
            }

            //send email to hcbp area
            $to = Model::getAckManager($data_esk->authority,$data_esk->new_directorate,$data_esk->tipe);
            $content = $this->renderPartial('../../mail/mail-delivered-hcbp',['esk' => $data_esk],true);
            Model::sendMailMultiple($to,$subject,$content);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully delivered!");
            return $this->redirect(['index']);
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed delivered eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed delivered eSK, because ".$error);
            return $this->redirect(['detail', 'id'=>$data_esk->id]);
        }
    }

    public function published($id_esk){
        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("published", '', $id_esk);
        $data_esk = EskLists::findOne($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        
        if($data_esk->save()){
            //get nik user 
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $nik = Yii::$app->user->identity->nik;
                $action = Yii::$app->user->identity->employee->title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                $published_by = Yii::$app->user->identity->employee->title;
            }else{
                $nik = "";
                $action = "HCBP Account/Area menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                $published_by = "HCBP Account/Area";
            }
            Model::setWorkFlow($data_esk->id,$action,"-");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$published_by);

            //submit posting career 
            Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

            $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
			$to = $data_esk->employee->email;
			$content = $this->renderPartial('../../mail/mail-published',['data_esk' => $data_esk],true);
            Model::sendNotifMoana($to,'My Assignment • New Update',ucwords(strtolower($data_esk->about_esk)));
			Model::sendMailOne($to,$subject,$content);

            //cek band
            $databp = explode(".",$data_esk->new_bp);                
            if($databp[0] == 5 || $databp[0] == 6){
                $to = Model::getDirectionMail($data_esk->new_directorate);
            }else{
                if($data_esk->authority == "HEAD OFFICE"){
                    $to = Model::getHCOA($data_esk->authority, $data_esk->old_directorate,$data_esk->tipe);//getHC();
                }else{
                    $to = Model::getHCBP('"'.$data_esk->authority.'"');
                }   
            }
            
            //send mail to hcbp area
            $subject = "[eSK] FYI Published of eSK Number ".$data_esk->number_esk."";
            $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk, 'nama_pengirim' => Yii::$app->user->identity->employee->nama],true);
            Model::sendMailMultiple($to,$subject,$content);
            
            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully published!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed published eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed published eSK, because ".$error);
        }

        return $this->redirect(['index']);        
    }

    public function actionModaldelivered(){
		set_time_limit(0);
		ini_set('memory_limit', '9048M');
		
        $id = yii::$app->request->get('id');
		
        return $this->renderAjax('deliverdialog',[
            "id" => $id,
        ]);
    }

    public function actionDeliveredall(){
		set_time_limit(0);
		ini_set('memory_limit', '9048M');
		
		$employee 	 = Yii::$app->user->identity->employee;
        $id_esk_data = yii::$app->request->get('id_esk');
		$id_esk = explode(",",$id_esk_data);
		
		//inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();
        
        foreach($id_esk as $id_esk){
			//kirim datanya ke fungsi workflow status
			$approval = EskApprovalLists::find()->where(['id_esk' => $id_esk])->one();
			$countPending = EskApprovalLists::find()->where(['id_esk' => $id_esk])->andWhere(['status' => 'pending'])->orWhere(['status' => 'rejected'])->count();
            $flag_data = EskFlagData::find()->one()->flag_ack;
            $count_ack = EskAcknowledgeLists::find()->where(['id_esk' => $id_esk])->count();
			$ack       = EskAcknowledgeLists::find()->where(['id_esk' => $id_esk])->andWhere(['ack_nik' => $employee->nik])->andWhere(['sequence' => 1])->one();
			
            if($count_ack > 0 && empty($ack)){
                $data_update = Model::WorkFlowStatus("delivered", $approval->id, $id_esk);
                $data_esk = $this->findModel($id_esk);
				//$real_data = $data_esk->content_esk;
                $data_esk->status = $data_update['status'];
                $data_esk->tracking = $data_update['tracking'];
                //update jika masih ada yg pending/bypass by hc staffing adm
                
				if($countPending > 0){
                    //update approved date 
                    $data_esk->approved_esk_date = date("Y-m-d");
                }
				
				//$data_esk->content_esk = $real_data;
				
                if($data_esk->save()){
                    //update status approval yg pending jadi skipped 
                    $data_app = EskApprovalLists::find()->where(['id_esk' => $id_esk,'status' => 'pending'])->all();
                    if(!empty($data_app)){
                        foreach ($data_app as $data_app) {
                            $data_app->status = "skipped by HCBP Account/Area";
                            $data_app->update(false);
                        }
                    }

                    //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
                    Model::setWorkFlow($data_esk->id,"HC Fungsi Staffing telah selesai memproses E-SK No.".$data_esk->number_esk,"-");

                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "HCBP Account/Area deliver eSK data with ID ".$data_esk->id);
					
                    //send mail
                    $subject = "[eSK] Delivered of eSK Number ".$data_esk->number_esk."";
                    if($flag_data == 1){
                        $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
                        $content = $this->renderPartial('../../mail/mail-delivered',['esk' => $data_esk, 'head' => $data_ack->ack_name],true);        
                        Model::sendMailOne($data_ack->ack_mail,$subject,$content);
                    }else{
                        $content = $this->renderPartial('../../mail/mail-delivered',['esk' => $data_esk, 'head' => $data_esk->vP->nama],true);        
                        Model::sendMailOne($data_esk->vP->email,$subject,$content);
                    }
					
					//var_dump($flag_data,$data_esk->content_esk);exit;
					
                    //cek band
                    $databp = explode(".",$data_esk->new_bp);                
                    if($databp[0] == 5 || $databp[0] == 6){
                        $to = Model::getDirectionMail($data_esk->new_directorate);
                    }else{
                        $to = Model::getAckManager($data_esk->authority,$data_esk->new_directorate,$data_esk->tipe);
                    }

                    //send email to hcbp area
                    $content = $this->renderPartial('../../mail/mail-delivered-hcbp',['esk' => $data_esk],true);
                    Model::sendMailMultiple($to,$subject,$content);

                    //set success count
                    $countSuccess = $countSuccess + 1;
					
                }else{
                    //set failed count
                    $countFailed = $countFailed + 1;
                    
                    //logging data
                    $error = implode(",",$data_esk->getErrorSummary(true));
                    array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed delivered eSK because ".$error);
                    Model::saveLog(Yii::$app->user->identity->username, "Failed delivered eSK data for ID ".$data_esk->id." because ".$error);
                }
            }else{
                $data_update = Model::WorkFlowStatus("published", '', $id_esk);
                $data_esk = EskLists::findOne($id_esk);
                $data_esk->status = $data_update['status'];
                $data_esk->tracking = $data_update['tracking'];
                
                if($data_esk->save()){
                    //get nik user 
                    if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                        $nik = Yii::$app->user->identity->nik;
                        $action = Yii::$app->user->identity->employee->title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                        $published_by = Yii::$app->user->identity->employee->title;
                    }else{
                        $nik = "";
                        $action = "HCBP Account/Area menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                        $published_by = "HCBP Account/Area";
                    }
                    Model::setWorkFlow($data_esk->id,$action,"-");

                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$published_by);
                    
                    //submit posting career 
                    Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

					//send mail to employee
                    $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
                    $to = $data_esk->employee->email;
                    $content = $this->renderPartial('../../mail/mail-published',['data_esk' => $data_esk],true);
                    Model::sendNotifMoana($to,'My Assignment • New Update',ucwords(strtolower($data_esk->about_esk)));
                    Model::sendMailOne($to,$subject,$content);
					
					//send mail to atasan langsung
					$subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
                    //$to = $data_esk->employee->email;
					$datakaryawan = Employee::findOne(['nik' => $data_esk->nik]);
					$atasan		  = Employee::findOne(['nik' => $datakaryawan->nik_atasan]);
                    $to 		  = $atasan->email;
					$content = $this->renderPartial('../../mail/mail-published-atasan-new',['data_esk' => $data_esk,'atasan' => $atasan],true);
                    Model::sendMailOne($to,$subject,$content);
					
                    //cek band
                    $databp = explode(".",$data_esk->new_bp);                
                    if($databp[0] == 5 || $databp[0] == 6){
                        $to = Model::getDirectionMail($data_esk->new_directorate);
                    }else{
                        $to = Model::getAckManager($data_esk->authority,$data_esk->new_directorate,$data_esk->tipe);
                    }
                    
                    //send mail to hcbp area
                    $subject = "[eSK] FYI Published of eSK Number ".$data_esk->number_esk."";
                    $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk, 'nama_pengirim' => Yii::$app->user->identity->employee->nama],true);
                    Model::sendMailMultiple($to,$subject,$content);
                    
                    //set success count
                    $countSuccess = $countSuccess + 1;
                }else{
                    //set failed count
                    $countFailed = $countFailed + 1;
                
                    //logging data
                    $error = implode(",",$data_esk->getErrorSummary(true));
                    Model::saveLog(Yii::$app->user->identity->username, "Failed published eSK data for ID ".$data_esk->id." because ".$error);
                    array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed delivered eSK because ".$error);
                }
            }
			
			//count iteration
            $countAll = $countAll + 1;
		}
		
		//check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
        }else{
            $failed_data = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully delivered ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data);

		return $this->redirect(['index']);	
    }

    public function actionCorrection(){
        $id_esk = yii::$app->request->get('id_esk_corr');
        $remark = yii::$app->request->get('remark');

        //kirim datanya ke fungsi workflow status
        $approval = EskApprovalLists::find()->where(['id_esk' => $id_esk])->andWhere(['sequence' => '1'])->one();
        $data_update = Model::WorkFlowStatus("correction", $approval->id, $id_esk);
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $data_esk->flag_approval_seq = $data_update['flag_approval_seq'];
        if($data_esk->save()){
            //update data approval
            $dataApproval = EskApprovalLists::find()->where(['id_esk' => $id_esk])->all();
            foreach($dataApproval as $app){
                $data_app = EskApprovalLists::findOne($app->id);
                $data_app->status = "pending";
                $data_app->approved_at = NULL;
                $data_app->rejected_at = NULL;
                $data_app->save();
            }

            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            Model::setWorkFlow($data_esk->id,"Pengembalian proses approval pembuatan E-SK ke Pemeriksa/Approval",$remark);

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "HCBP Account/Area correction eSK data with ID ".$data_esk->id);

            
            //send mail ke approval pertama
            $subject = "[eSK] Request of Approval eSK Number ".$data_esk->number_esk."";
            $content = $this->renderPartial('../../mail/mail-approval',['esk' => $data_esk, 'approval' => $approval->approval_name, 'remark' => $remark],true);        
            Model::sendMailOne($approval->approval_mail,$subject,$content);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully correction!");
            return $this->redirect(['index']);
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed correction eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed correction eSK, because ".$error);
            return $this->redirect(['detail', 'id'=>$data_esk->id]);
        }
    }

    public function actionCancel(){
        $id_esk = yii::$app->request->get('id_esk_cancel');

        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("cancel", '', $id_esk);
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $data_esk->flag_approval_seq = $data_update['flag_approval_seq'];
        if($data_esk->save()){
            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            Model::setWorkFlow($data_esk->id,"Pembatalan E-SK oleh HCBP Account/Area","");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "HCBP Account/Area cancel eSK data with ID ".$data_esk->id);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully cancel!");
            return $this->redirect(['index']);
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed cancel eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed cancel eSK, because ".$error);
            return $this->redirect(['detail', 'id'=>$data_esk->id]);
        }
    }

    public function actionReassign($id){
        $model = EskApprovalLists::findOne($id);
        if (Yii::$app->request->post()) {
            $request =  Yii::$app->request->post();
            $emp = $request['reassign-app'];
            
            //check apakah update atau tidak 
            if(strpos($model->approval_nik, $emp) === false){
                //cari data by nik 
                $dataEmp = Employee::find()->where(['nik' => $emp])->one();
                if(!empty($dataEmp)){
                    $model->approval_nik = $emp;
                    $model->approval_name = $dataEmp->nama;
                    $model->approval_mail = $dataEmp->email;
                    $model->approval_title = $dataEmp->title;
                    if($model->save()){
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Reassign approval of eSK Lists with ID Approval ".$model->id);

                        //set flash berhasil reassign
                        Yii::$app->session->setFlash('success', "Approval of eSK data successfully reassign!");
                    }else{
                        //logging data
                        $error = implode(",",$model->getErrorSummary(true));
                        Model::saveLog(Yii::$app->user->identity->username, "Failed reassign approval of eSK data for ID Approval ".$model->id." because ".$error);
                        
                        //set flassh failed reassign
                        Yii::$app->session->setFlash('error', "Failed reassign approval of eSK, because ".$error);
                    }
                }else{
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Failed reassign approval of eSK data because employee data not found!");
                    Yii::$app->session->setFlash('error', "Failed reassign approval of eSK, employee data not found.");
                }
            }else{
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Failed reassign approval of eSK data because employee is same!");
                Yii::$app->session->setFlash('error', "Failed reassign approval of eSK, employee data is same with approval.");
            }
    
            //balik ke detail
            return $this->redirect(['detail', 'id'=>$model->id_esk]);
        }

        return $this->renderAjax('reassign', [
            'model' => $model,
        ]);
    }

    public function actionSkipped($id){
        $model = EskApprovalLists::findOne($id);
        $id_esk = $model->id_esk;

        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("approved", $id, $id_esk);
        $data_esk = EskLists::findOne($id_esk);
        $data_esk->flag_approval_seq = $data_update['flag_approval_seq'];
        if($data_esk->save()){
            $model->status = "skipped by HCBP Account/Area";
            if($model->save()){
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Skipped approval of eSK Lists with ID Approval ".$model->id);
    
                //set flash berhasil reassign
                Yii::$app->session->setFlash('success', "Approval of eSK data successfully skipped!");
            }else{
                //logging data
                $error = implode(",",$model->getErrorSummary(true));
                Model::saveLog(Yii::$app->user->identity->username, "Failed skipped approval of eSK data for ID ".$model->id." because ".$error);
                
                //set flassh failed reassign
                Yii::$app->session->setFlash('error', "Failed skipped approval of eSK, because ".$error);
            }
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed skipped approval of eSK data for ID ".$data_esk->id." because ".$error);
            
            //set flassh failed reassign
            Yii::$app->session->setFlash('error', "Failed skipped approval of eSK, because ".$error);
        }
        
        //balik ke detail
        return $this->redirect(['detail', 'id'=>$model->id_esk]);
    }

    public function actionCorrectionCreate(){
        $id_esk = yii::$app->request->get('id_esk_corr');
        $data_esk = $this->findModel($id_esk);
        $flag_success = false;

        //update berita acara detail flag
        $ba_detail = GenerateEsk::findOne($data_esk->id_ba_detail);
        $ba_detail->flag_esk = 0;
        $ba_detail->save();

        if($ba_detail->save()){
            if($data_esk->delete()){
                //hapus semua workflow 
                $workflow = EskWorkflowLists::find()->where(['id_esk' => $id_esk])->one();
                $workflow->delete();

                $flag_success = true;

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Successfully return eSK data for recreate again.".$id_esk);
    
                //set flash berhasil reassign
                Yii::$app->session->setFlash('success', "eSK data successfully returned!");
            }else{
                //logging data
                $error = implode(",",$data_esk->getErrorSummary(true));
                Model::saveLog(Yii::$app->user->identity->username, "Failed delete of eSK data for ID ".$id_esk." because ".$error);
                
                //set flassh failed reassign
                Yii::$app->session->setFlash('error', "Failed correction of eSK data, because ".$error);
            }
        }else{
            //logging data
            $error = implode(",",$ba_detail->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed update BA detail of eSK data for ID ".$data_esk->id." because ".$error);
            
            //set flassh failed reassign
            Yii::$app->session->setFlash('error', "Failed correction of eSK data, because ".$error);
        }
        
        //balik ke index jika success
        if($flag_success){
            return $this->redirect(['generated']);
        }else{
            return $this->redirect(['view','id_batch' => $data_esk->id_batch]);
        }
    }

    public function actionCancelCreate(){
        $id_esk = yii::$app->request->get('id_esk_cancel');
        $data_update = Model::WorkFlowStatus("cancel", '', $id_esk);
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $count = StaffingLists::find()->where(['id_batch' => $data_esk->id_batch])->count();
        $data_esk->new_bi = empty($data_esk->new_bi) ? $data_esk->old_bi : $data_esk->new_bi;

        if($data_esk->save()){
            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            Model::setWorkFlow($data_esk->id,"Pembatalan E-SK oleh HCBP Account/Area","");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "HCBP Account/Area cancel eSK data with ID ".$data_esk->id);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully canceled!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed cancel eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed cancel eSK, because ".$error);
        }

        if($count <= 1){
            return $this->redirect(['generated']);
        }
        return $this->redirect(['view','id_batch' => $data_esk->id_batch]);
    }

    /**
     * Finds the EskLists model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskLists the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = StaffingLists::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionPositionlist($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(['nama AS id', 'nama AS text'])
                ->from('position')
                ->where(['like', 'nama', $q])
                ->andWhere(['status' => 1]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Position::find($id)->nama];
        }
        return $out;
    }

    public function actionPositionListid($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(['id AS id', 'nama AS text'])
                ->from('position')
                ->where(['like', 'nama', $q])
                ->andWhere(['status' => 1]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Position::find($id)->nama];
        }
        return $out;
    }

    public function actionGetPosition($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(["id AS id", "CONCAT(nama,' (', bp,'/',desc_city,')') AS text"])
                ->from('position')
                ->where(['like', 'nama', $q])
                ->andWhere(['status' => 1]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Position::find($id)->nama];
        }
        return $out;
    }

    public function actionGetPositionBa($q = null, $id = null, $band = null) {
        if(empty($band) || $band == 0){
            $query_search = "";
        }else{
            $new_band = $band + 1;
            $query_search = 'band >= '.$band.' AND band <= '.$new_band;
        }

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query1 = new \yii\db\Query;
            $query1
                ->select(["id AS id", "CONCAT(nama,' (', bp,'/',desc_city,')') AS text"])
                ->from('position')
                ->where(['like', 'nama', $q])
                ->andWhere(['status' => 1])
                ->andWhere('nama NOT LIKE "%Senior Staff%"')
                ->andWhere($query_search);
            $query2 = new \yii\db\Query;
            $query2
                ->select(["CONCAT('101') AS id", "CONCAT('Senior Staff') AS text"]);
            $query_all = $query1->union($query2);    
            $command = $query_all->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Position::find($id)->nama];
        }
        return $out;
    }

    public function actionGetOrganization($q = null, $id = null, $band = null) {
        if(empty($band) || $band == 0){
            $query_search = "";
        }else{
            $new_band = $band + 1;
            $query_search = 'band >= '.$band.' AND band <= '.$new_band;
        }

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(["id AS id", "CONCAT(organization,' (Type of ', organization_type,')') AS text"])
                ->from('position')
                ->where(['status' => 1])
                ->andWhere($query_search)
                ->having('text LIKE "%'.$q.'%"')
                ->groupBy('text');
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Position::find($id)->nama];
        }
        return $out;
    }

    // add by faqih sprint 4
    public function actionListspos($q = null)
    {   
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(['position.id AS idpos', 'position.nama AS id', 'position.nama AS text', 'position.organization', 'position.department', 'position.division', 'position.grp AS bgroup', 'position.egrp AS egroup', 'position.directorate', 'UPPER(position.desc_city) AS city', 'area', 'band', 'bp', 'esk_tunjab_nss.tunjab AS tunjab_bss'])
                ->from('position')
                ->join('LEFT JOIN', 'esk_tunjab_nss', 'esk_tunjab_nss.level = position.band')
                ->where(['nama' => $q])
                ->andWhere(['status' => 1])
                ->andwhere(['not',['position_code' => null]])
                ->andwhere(['not',['band' => null]])
                ->andwhere(['not',['directorate' => null]]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        return $out;
    }
    // 
}
