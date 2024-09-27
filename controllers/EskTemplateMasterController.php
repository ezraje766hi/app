<?php

namespace esk\controllers;

use Yii;
use esk\models\EskTemplateMaster;
use esk\models\EskTemplateMasterSearch;
use esk\models\EskTemplateDetail;
use esk\models\EskTemplateDetailSearch;
use esk\models\EskTemplateAuthority;
use esk\models\EskTemplateAuthoritySearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use kartik\mpdf\Pdf;

use esk\models\EskGroupReasonData;

/**
 * EskTemplateMasterController implements the CRUD actions for EskTemplateMaster model.
 */
class EskTemplateMasterController extends Controller
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
     * Lists all EskTemplateMaster models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskTemplateMasterSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskTemplateMaster model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {   
        $model = $this->findModel($id);
        $searchModel = new EskTemplateDetailSearch();
        $details = $searchModel->findDetails($id);
        $searchModel2 = new EskTemplateAuthoritySearch();
        $authorities = $searchModel2->findAuthorities($id);

        // add by faqih
        $reasondt = EskGroupReasonData::findOne($model->id_reason);
        // end

        return $this->render('view', [
            'model' => $model,
            'details' => $details,
            'authorities' => $authorities,
            'reason' => $reasondt['group'].' - '.$reasondt['reason'], // add by faqih
        ]);
    }

    public function actionPreview($id)
    {   
        $model = $this->findModel($id);

        //validasi interim
        $type = strtolower($model->type);
        $flag_interim = (strpos($type,"interim") !== false) ? ".IN" : "";
        
        //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
        //$content_sk = Model::generateEsk($id,"","1","","1");
		$content_sk = Model::generateEsk($id,"","1","","1","","","","","","","","",1);
        $esk_no = "00/TEMPLATE-SK.00".$flag_interim."/HC-00/" . Model::getMonthYear(date("m"),date("Y")); 
        $all_content = Model::setEskData($model->id,$model->about,$esk_no,$content_sk,"Jakarta Selatan","Nama Karyawan","000000","Jabatan Karyawan","1","Direktur Human Capital Management",date("Y-m-d"),"-","preview");

        return $this->render('preview', [
            'model' => $model,
            'content' => $all_content,
        ]);
    }

    /**
     * Creates a new EskTemplateMaster model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        // die('disini');
        $model = new EskTemplateMaster();
        $details = [new EskTemplateDetail];
        $authorities = [new EskTemplateAuthority];
        // add by faqih
        $reason = new EskGroupReasonData;
        // end
        
        //proses post variabel
        if ($model->load(Yii::$app->request->post())) {
            
            $details = Model::createMultiple(EskTemplateDetail::classname());
            $authorities = Model::createMultiple(EskTemplateAuthority::classname());
            // $reason = Model::createMultiple(EskGroupReasonData::classname());
            Model::loadMultiple($details, Yii::$app->request->post());
            Model::loadMultiple($authorities, Yii::$app->request->post());
            // Model::loadMultiple($reason, Yii::$app->request->post());
            
            // assign default transaction_id
            foreach ($details as $detail) {
                $detail->id_esk_master = 0;
            }
            
            foreach ($authorities as $authority) {
                $authority->id_esk_master = 0;
            }
            // var_dump(Yii::$app->request->post());
            // die('disini');
            // ajax validation
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ArrayHelper::merge(
                    ActiveForm::validateMultiple($details),
                    ActiveForm::validateMultiple($authorities),
                    ActiveForm::validate($model),
                    ActiveForm::validate($reason)
                );
            }
            
            // validate all models
            $valid1 = $model->validate();
            $valid2 = Model::validateMultiple($details);
            $valid3 = Model::validateMultiple($authorities);
            $valid = $valid1 && $valid2 && $valid3;

            // jika valid, mulai proses penyimpanan
            if ($valid) {
                // mulai database transaction
                $transaction = \Yii::$app->db->beginTransaction();
            // var_dump($transaction);
            // die('disini');
                try {
                    // simpan master record                   
                    if ($flag = $model->save(false)) {
                        // kemudian simpan detail records
                        foreach ($details as $detail) {
                            $detail->id_esk_master = $model->id;
                            if (! ($flag = $detail->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }

                        foreach ($authorities as $authority) {
                            $authority->id_esk_master = $model->id;
                            if (! ($flag = $authority->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }
                    }

                    if ($flag) {
                        // sukses, commit database transaction
                        // kemudian tampilkan hasilnya
                        $transaction->commit();
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Template with ID ".$model->id);
                
                        Yii::$app->session->setFlash('success', "Your esk template successfully created.");
                        return $this->redirect(['esk-template-master/index']); 
                    } else {
                        Yii::$app->session->setFlash('error', "Your esk template was not saved.");
                        return $this->redirect(['esk-template-master/index']); 
                    }
                } catch (Exception $e) {
                    // penyimpanan gagal, rollback database transaction
                    $transaction->rollBack();
                    throw $e;
                }
            } else {
                return $this->render('create', [
                    'model' => $model,
                    'details' => $details,
                    'authorities' => $authorities,
                    'reason' => $reason,
                    'error' => 'valid1: '.print_r($valid1,true).' - valid2: '.print_r($valid2,true).' - valid3: '.print_r($valid3,true),
                ]);
            }
        }else{
            // die('disitu');
            // inisialisai id 
            // diperlukan untuk form master-detail
            $model->id = 0;
            $model->is_active = true;
            // render view
            return $this->render('create', [
                'model' => $model,
                'details' => $details,
                'authorities' => $authorities,
                'reason' => $reason,
            ]);
        }
    }

    public function actionCopy($id)
    {
        $model = new EskTemplateMaster();
        $model2 = $this->findModel($id); 
        $details = $model2->details;
        $authorities = $model2->authoritys;

        //proses post variabel
        if ($model->load(Yii::$app->request->post())) {
            $details = Model::createMultiple(EskTemplateDetail::classname());
            $authorities = Model::createMultiple(EskTemplateAuthority::classname());
            Model::loadMultiple($details, Yii::$app->request->post());
            Model::loadMultiple($authorities, Yii::$app->request->post());

            // assign default transaction_id
            foreach ($details as $detail) {
                $detail->id_esk_master = 0;
            }
            
            foreach ($authorities as $authority) {
                $authority->id_esk_master = 0;
            }

            // ajax validation
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ArrayHelper::merge(
                    ActiveForm::validateMultiple($details),
                    ActiveForm::validateMultiple($authorities),
                    ActiveForm::validate($model)
                );
            }

            // validate all models
            $valid1 = $model->validate();
            $valid2 = Model::validateMultiple($details);
            $valid3 = Model::validateMultiple($authorities);
            $valid = $valid1 && $valid2 && $valid3;

            // jika valid, mulai proses penyimpanan
            if ($valid) {
                // mulai database transaction
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    // simpan master record                   
                    if ($flag = $model->save(false)) {
                        // kemudian simpan detail records
                        foreach ($details as $detail) {
                            $detail->id_esk_master = $model->id;
                            if (! ($flag = $detail->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }

                        foreach ($authorities as $authority) {
                            $authority->id_esk_master = $model->id;
                            if (! ($flag = $authority->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }
                    }

                    if ($flag) {
                        // sukses, commit database transaction
                        // kemudian tampilkan hasilnya
                        $transaction->commit();
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Template with ID ".$model->id);
                
                        Yii::$app->session->setFlash('success', "Your esk template successfully created.");
                        return $this->redirect(['esk-template-master/index']); 
                    } else {
                        Yii::$app->session->setFlash('error', "Your esk template was not saved.");
                        return $this->redirect(['esk-template-master/index']); 
                    }
                } catch (Exception $e) {
                    // penyimpanan gagal, rollback database transaction
                    $transaction->rollBack();
                    throw $e;
                }
            } else {
                return $this->render('create', [
                    'model' => $model,
                    'details' => $details,
                    'authorities' => $authorities,
                    'error' => 'valid1: '.print_r($valid1,true).' - valid2: '.print_r($valid2,true).' - valid3: '.print_r($valid3,true),
                ]);
            }
        }else{
            // inisialisai id 
            // diperlukan untuk form master-detail
            $model->id = 0;
            $model->is_active = true;
            // render view
            return $this->render('create', [
                'model' => $model,
                'details' => (empty($details)) ? [new EskTemplateDetail] : $details,
                'authorities' => (empty($authorities)) ? [new EskTemplateAuthority] : $authorities
            ]);
        }
    }

    /**
     * Updates an existing EskTemplateMaster model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id); 
        $details = $model->details;
        $authorities = $model->authoritys;

        
        // add by faqih
        $reason = new EskGroupReasonData;
        // end

        if ($model->load(Yii::$app->request->post())) {
            //data template detail
            $oldIDs = ArrayHelper::map($details, 'id', 'id');
            $details = Model::createMultiple(EskTemplateDetail::classname(), $details);
            Model::loadMultiple($details, Yii::$app->request->post());
            $deletedIDs = array_diff($oldIDs, array_filter(ArrayHelper::map($details, 'id', 'id')));

            //data tempalte authority
            $oldAuthIDs = ArrayHelper::map($authorities, 'id', 'id');
            $authorities = Model::createMultiple(EskTemplateAuthority::classname(), $authorities);
            Model::loadMultiple($authorities, Yii::$app->request->post());
            if(!empty($authorities)){
                $deletedAuthIDs = array_diff($oldAuthIDs, array_filter(ArrayHelper::map($authorities, 'id', 'id')));

            }
   
            // assign default transaction_id
            foreach ($details as $detail) {
                $detail->id_esk_master= $model->id;
            }

            foreach ($authorities as $authority) {
                $authority->id_esk_master= $model->id;
            }

            // ajax validation
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ArrayHelper::merge(
                    ActiveForm::validateMultiple($details),
                    ActiveForm::validateMultiple($authorities),
                    ActiveForm::validate($model)
                );
            }

            // validate all models
            $valid1 = $model->validate();
            $valid2 = Model::validateMultiple($details);
            $valid3 = Model::validateMultiple($authorities);
            $valid = $valid1 && $valid2 && $valid3;

            // jika valid, mulai proses penyimpanan
            if ($valid) {
                // mulai database transaction
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    // simpan master record                   
                    if ($flag = $model->save(false)) {
                        // delete dahulu semua record yang ada
                        if (!empty($deletedIDs)) {
                            EskTemplateDetail::deleteAll(['id' => $deletedIDs]);
                        }
                        if (!empty($deletedAuthIDs)) {
                            EskTemplateAuthority::deleteAll(['id' => $deletedAuthIDs]);
                        }
                        // kemudian, simpan details record
                        foreach ($details as $detail) {
                            $detail->id_esk_master = $model->id;
                            if (!($flag = $detail->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }
                        foreach ($authorities as $authority) {
                            $authority->id_esk_master = $model->id;
                            if (!($flag = $authority->save(false))) {
                                $transaction->rollBack();
                                break;
                            }
                        }
                    }
                    if ($flag) {
                        // sukses, commit database transaction
                        // kemudian tampilkan hasilnya
                        $transaction->commit();

                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Update eSK Template with ID ".$model->id);
                
                        Yii::$app->session->setFlash('success', "Your esk template successfully updated."); 
                        return $this->redirect(['esk-template-master/index']); 
                    }else{
                        Yii::$app->session->setFlash('error', "Your esk template was not updated.");
                        return $this->redirect(['esk-template-master/index']); 
                    }
                } catch (Exception $e) {
                    // penyimpanan galga, rollback database transaction
                    $transaction->rollBack();
                    throw $e;
                }
            } else {
                return $this->render('update', [
                    'model' => $model,
                    'details' => $details,
                    'authorities' => $authorities,
                    'error' => 'valid1: '.print_r($valid1,true).' - valid2: '.print_r($valid2,true).' - valid3: '.print_r($valid3,true),
                    'reason' => $reason,
                ]);
            }
        }
        // render view
        return $this->render('update', [
            'model' => $model,
            'details' => (empty($details)) ? [new EskTemplateDetail] : $details,
            'authorities' => (empty($authorities)) ? [new EskTemplateAuthority] : $authorities,
            'reason' => (empty($reason)) ? [new EskGroupReasonData] : $reason,
        ]);
    }

    /**
     * Deletes an existing EskTemplateMaster model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {   
        $model = $this->findModel($id);
        $details = $model->details;
        $authorities = $model->authoritys;

         // mulai database transaction
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // pertama, delete semua detail records
            foreach ($details as $detail) {
                if(!empty($detail->id)){
                    EskTemplateDetail::deleteAll(['id' => $detail->id]);
                }
            }

            foreach ($authorities as $authority) {
                if(!empty($authority->id)){
                    EskTemplateAuthority::deleteAll(['id' => $authority->id]);
                }
            }

            // kemudian, delete master record
            if ($model->delete()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Template with ID ".$id);
                
                Yii::$app->session->setFlash('success', "Your esk template successfully deleted."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk template was not deleted.");
            }
            // sukses, commit transaction
            $transaction->commit();
    
        } catch (Exception $e) {
            // gagal, rollback database transaction
            $transaction->rollBack();
        }

        return $this->redirect(['esk-template-master/index']);
    }

    public function actionPrint($id,$flag_print)
    {   
        $model = $this->findModel($id);
        
        //validasi interim
        $type = strtolower($model->type);
        $flag_interim = (strpos($type,"interim") !== false) ? ".IN" : "";
        
        //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
        $content_sk = Model::generateEsk($id,"","1","","1");
        $esk_no = "00/TEMPLATE-SK.00".$flag_interim."/HC-00/" . Model::getMonthYear(date("m"),date("Y")); 

        //logging data
        Model::saveLog(Yii::$app->user->identity->username, "Print Template eSK with ID ".$model->id);
        
        if($flag_print == 1){
            $all_content = Model::setEskData($model->id,$model->about,$esk_no,$content_sk,"Jakarta Selatan","Nama Karyawan","000000","Jabatan Karyawan","1","Direktur Human Capital Management",date("Y-m-d"),"-","print","1");

            if($model->page_break_content != 0 || $model->page_break_content != "0"){
                //get page break data
                $data_content = Model::setPageBreak($model->id,$model->page_break_content,$all_content);

                if($data_content['is_pagebreak'] == 1){
                    //print dengan page break dan footer
                    $pdf = new Pdf([
                        'mode' => Pdf::MODE_UTF8, 
                        'format' => Pdf::FORMAT_A4, 
                        'orientation' => Pdf::ORIENT_PORTRAIT, 
                        'defaultFontSize' => 8,
                        'marginLeft' => 16,
                        'marginRight' => 16,
                        'marginTop' => 20,
                        'marginBottom' =>7,
                        'marginHeader' => 8,
                        'marginFooter' => 8,
                        'filename' => "Template Surat Keputusan tentang ".$model->about.".pdf",
                        'destination' => Pdf::DEST_DOWNLOAD, //Pdf::DEST_DOWNLOAD
                        'content' => str_replace("font-size:8pt","font-size:9pt",$data_content['content']),   
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',  
                        'cssInline' => '
                            @media print{
                                .page-break{display: block;page-break-before: always;}
                            }
                        ',
                        'methods' => [ 
                            'SetHtmlFooter'=>['
                            <table width="100%" style="font-family:arial;font-size:7pt;">
                                <tr>
                                    <td width="40%">Halaman {PAGENO}/{nbpg}</td>
                                    <td width="60%" style="text-align: right;">eSK Nomor: '.$esk_no.'</td>
                                </tr>
                            </table>
                            '],
                        ]
                    ]);
    
                    return $pdf->render();
                }else{
                    //print default tanpa page break dan footer
                    $pdf = new Pdf([
                        'mode' => Pdf::MODE_UTF8, 
                        'format' => Pdf::FORMAT_A4, 
                        'orientation' => Pdf::ORIENT_PORTRAIT, 
                        'defaultFontSize' => 8,
                        'marginLeft' => 16,
                        'marginRight' => 16,
                        'marginTop' => 20,
                        'marginBottom' =>7,
                        'marginHeader' => 8,
                        'marginFooter' => 8,
                        'filename' => "Template Surat Keputusan tentang ".$model->about.".pdf",
                        'destination' => Pdf::DEST_DOWNLOAD, //Pdf::DEST_DOWNLOAD
                        'content' => $all_content,   
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css'
                    ]);
                    return $pdf->render();
                }
            }else{
                //print default tanpa page break dan footer
                $pdf = new Pdf([
                    'mode' => Pdf::MODE_UTF8, 
                    'format' => Pdf::FORMAT_A4, 
                    'orientation' => Pdf::ORIENT_PORTRAIT, 
                    'defaultFontSize' => 8,
                    'marginLeft' => 16,
                    'marginRight' => 16,
                    'marginTop' => 20,
                    'marginBottom' =>7,
                    'marginHeader' => 8,
                    'marginFooter' => 8,
                    'filename' => "Template Surat Keputusan tentang ".$model->about.".pdf",
                    'destination' => Pdf::DEST_DOWNLOAD, //Pdf::DEST_DOWNLOAD
                    'content' => $all_content,   
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css'
                ]);
                return $pdf->render();
            }
        }else{
            $all_content = Model::setEskData($model->id,$model->about,$esk_no,$content_sk,"Jakarta Selatan","Nama Karyawan","000000","Jabatan Karyawan","1","Direktur Human Capital Management",date("Y-m-d"),"-","preview","1");

            return $this->renderPartial('//site/print_plain', [
                'content' => $all_content,
            ]);
        }
    }

    /**
     * Finds the EskTemplateMaster model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskTemplateMaster the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskTemplateMaster::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionLists($type){
        //AND TYPE NOT LIKE "%Rota%" AND TYPE NOT LIKE "%Prom%" TYPE NOT LIKE "%Eva%") AND 
        //AND TYPE NOT LIKE "%Rota%" AND TYPE NOT LIKE "%Prom%" TYPE NOT LIKE "%Eva%") AND 
        
        $countContent = EskTemplateMaster::find()->select(['type','code_template','title'])
            ->where('TYPE ="'.$type.'"')
            ->distinct()->count();
        $contents = EskTemplateMaster::find()->select(['type','code_template','title'])
            ->where('TYPE ="'.$type.'"')
            ->distinct()->all();
        if($countContent > 0)
        {
            foreach ($contents as $content) {
                echo "<option value='" .$content->code_template. "'>
                    ".$content->code_template." <span style='font-size:8pt;'>(".$content->title.")</span>
                </option>";
            }
        }
        else
        {
            echo "<option></option>";
        }
    }

    public function actionValidationContent($code){
        $esk_master = EskTemplateMaster::find()->where(['code_template' => $code])->one();
        //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
        //$content_sk = Model::generateEsk($esk_master->id,"","1","","1");
		  $content_sk = Model::generateEsk($esk_master->id,"","1","","1","","","","","","","","",1);
		  
        //cek code
        $flag_nama_penyakit = (strpos($content_sk, "{nama_penyakit}") !== false) ? 1 : 0;
        $flag_nominal_insentif = (strpos($content_sk, "{nominal_insentif}") !== false) ? 1 : 0;
        $flag_periode = (strpos($content_sk, "{periode}") !== false) ? 1 : 0;
        $flag_nota_dinas = (strpos($content_sk, "{nota_dinas}") !== false) ? 1 : 0;
        $flag_ba_1 = (strpos($content_sk, "{keterangan_ba_1}") !== false) ? 1 : 0;
        $flag_ba_2 = (strpos($content_sk, "{keterangan_ba_2}") !== false) ? 1 : 0;
        $flag_ba_3 = (strpos($content_sk, "{keterangan_ba_3}") !== false) ? 1 : 0;
        $flag_kd_1 = (strpos($content_sk, "{keputusan_direksi_1}") !== false) ? 1 : 0;
        $flag_kd_2 = (strpos($content_sk, "{keputusan_direksi_2}") !== false) ? 1 : 0;
        $flag_kd_3 = (strpos($content_sk, "{keputusan_direksi_3}") !== false) ? 1 : 0;
        $flag_cltp_reason = (strpos($content_sk, "{cltp_reason}") !== false) ? 1 : 0;
        $flag_scholar_program = (strpos($content_sk, "{scholarship_program}") !== false) ? 1 : 0;
        $flag_scholar_university = (strpos($content_sk, "{scholarship_university}") !== false) ? 1 : 0;
        $flag_scholar_level = (strpos($content_sk, "{scholarship_level}") !== false) ? 1 : 0;
        $flag_start_sick = (strpos($content_sk, "{start_date_sick}") !== false) ? 1 : 0;
        $flag_end_sick = (strpos($content_sk, "{end_date_sick}") !== false) ? 1 : 0;
        $flag_phk_date = (strpos($content_sk, "{phk_date}") !== false) ? 1 : 0;
        $flag_statement_date = (strpos($content_sk, "{tanggal_td_pernyataan}") !== false) ? 1 : 0;
        $flag_last_payroll = (strpos($content_sk, "{last_payroll}") !== false) ? 1 : 0;
        $flag_resign_date = (strpos($content_sk, "{resign_date}") !== false) ? 1 : 0;
        $flag_kisel = (strpos($esk_master->type,"PHK") !== false) ? 1 : 0;
		$flag_gaji_dasar_nss = strpos($content_sk,"{gaji_dasar_nss}") !== false ? '1' : '0'; 
		$flag_tbh_nss = strpos($content_sk,"{tbh_nss}") !== false ? '1' : '0'; 
		$flag_tunjangan_rekomposisi_nss = strpos($content_sk,"{tunjangan_rekomposisi_nss}") !== false ? '1' : '0'; 
		$flag_tunjab_nss = strpos($content_sk,"{tunjab_nss}") !== false ? '1' : '0'; 
				
        $data = array(
            'flag_nama_penyakit' => $flag_nama_penyakit,
            'flag_nominal_insentif' => $flag_nominal_insentif,
            'flag_periode' => $flag_periode,
            'flag_nota_dinas' => $flag_nota_dinas,
            'flag_ba_1' => $flag_ba_1,
            'flag_ba_2' => $flag_ba_2,
            'flag_ba_3' => $flag_ba_3,
            'flag_kd_1' => $flag_kd_1,
            'flag_kd_2' => $flag_kd_2,
            'flag_kd_3' => $flag_kd_3,
            'flag_cltp_reason' => $flag_cltp_reason,
            'flag_scholar_program' => $flag_scholar_program,
            'flag_scholar_university' => $flag_scholar_university,
            'flag_scholar_level' => $flag_scholar_level,
            'flag_start_sick' => $flag_start_sick,
            'flag_end_sick' => $flag_end_sick,
            'flag_phk_date' => $flag_phk_date,
            'flag_statement_date' => $flag_statement_date,
            'flag_last_payroll' => $flag_last_payroll,
            'flag_resign_date' => $flag_resign_date,
            'flag_kisel' => $flag_kisel,
			'flag_gaji_dasar_nss' => $flag_gaji_dasar_nss,
			'flag_tbh_nss' => $flag_tbh_nss,
			'flag_tunjangan_rekomposisi_nss' => $flag_tunjangan_rekomposisi_nss ,
			'flag_tunjab_nss' => $flag_tunjangan_rekomposisi_nss,
        );

        return json_encode($data);
    }
}
