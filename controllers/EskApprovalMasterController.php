<?php

namespace esk\controllers;

use Yii;
use esk\models\EskApprovalMaster;
use esk\models\EskApprovalMasterSearch;
use esk\models\EskApprovalDetail;
use esk\models\EskApprovalDetailSearch;
use esk\models\Employee;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\filters\AccessControl;

/**
 * EskApprovalMasterController implements the CRUD actions for EskApprovalMaster model.
 */
class EskApprovalMasterController extends Controller
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
                        'roles' => ['sysadmin','hc_staffing','hcbp_account'],
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
     * Lists all EskApprovalMaster models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskApprovalMasterSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskApprovalMaster model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {   
        $model = $this->findModel($id);
        $searchModel = new EskApprovalDetailSearch();
        $details = $searchModel->findDetails($id);

        return $this->render('view', [
            'model' => $model,
            'details' => $details,
        ]);
    }

    /**
     * Creates a new EskApprovalMaster model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskApprovalMaster();
        $details = [new EskApprovalDetail];

        //proses post variabel
        if ($model->load(Yii::$app->request->post())) {
            //save data directorate multiple 
            $request = Yii::$app->request->post();
            $directorate_array = $request['EskApprovalMaster']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
			$authority_area_array = $request['EskApprovalMaster']['authority_area'];
            $authority_area = implode(",",$authority_area_array);
            $model->authority_area = $authority_area;
			
            $details = Model::createMultiple(EskApprovalDetail::classname());
            Model::loadMultiple($details, Yii::$app->request->post());

            // assign default transaction_id
            foreach ($details as $detail) {
                $detail->id_approval_master = 0;
            }

            // ajax validation
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ArrayHelper::merge(
                    ActiveForm::validateMultiple($details),
                    ActiveForm::validate($model)
                );
            }

            // validate all models
            $valid1 = $model->validate();
            $valid2 = Model::validateMultiple($details);
            $valid = $valid1 && $valid2;

            // jika valid, mulai proses penyimpanan
            if ($valid) {
                // mulai database transaction
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    // simpan master record                   
                    if ($flag = $model->save(false)) {
                        // kemudian simpan detail records
                        foreach ($details as $detail) {
                            $detail->id_approval_master = $model->id;
                            if (! ($flag = $detail->save(false))) {
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
                        Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Approval with ID ".$model->id);
                
                        Yii::$app->session->setFlash('success', "Your esk approval successfully created."); 
                        return $this->redirect(['esk-approval-master/index']); 
                    } else {
                        Yii::$app->session->setFlash('error', "Your esk approval was not saved.");
                        return $this->redirect(['esk-approval-master/index']); 
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
                    'error' => 'valid1: '.print_r($valid1,true).' - valid2: '.print_r($valid2,true),
                ]);
            }
        }else{
            // inisialisai id 
            // diperlukan untuk form master-detail
            $model->id = 0;
            // render view
            return $this->render('create', [
                'model' => $model,
                'details' => $details,
            ]);
        }
    }

    /**
     * Updates an existing EskApprovalMaster model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {   
        $model = $this->findModel($id); 
        $details = $model->details;

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();
            $directorate_array = $request['EskApprovalMaster']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
			$authority_area_array = $request['EskApprovalMaster']['authority_area'];
            $authority_area = implode(",",$authority_area_array);
            $model->authority_area = $authority_area;
			
            //data template detail
            $oldIDs = ArrayHelper::map($details, 'id', 'id');
            $details = Model::createMultiple(EskApprovalDetail::classname(), $details);
            Model::loadMultiple($details, Yii::$app->request->post());
            $deletedIDs = array_diff($oldIDs, array_filter(ArrayHelper::map($details, 'id', 'id')));

            // assign default transaction_id
            foreach ($details as $detail) {
                $detail->id_approval_master= $model->id;
            }

            // ajax validation
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ArrayHelper::merge(
                    ActiveForm::validateMultiple($details),
                    ActiveForm::validate($model)
                );
            }

            // validate all models
            $valid1 = $model->validate();
            $valid2 = Model::validateMultiple($details);
            $valid = $valid1 && $valid2;

            // jika valid, mulai proses penyimpanan
            if ($valid) {
                // mulai database transaction
                $transaction = \Yii::$app->db->beginTransaction();
                try {
                    // simpan master record                   
                    if ($flag = $model->save(false)) {
                        // delete dahulu semua record yang ada
                        if (! empty($deletedIDs)) {
                            EskApprovalDetail::deleteAll(['id' => $deletedIDs]);
                        }

                        // kemudian, simpan details record
                        foreach ($details as $detail) {
                            $detail->id_approval_master = $model->id;
                            if (! ($flag = $detail->save(false))) {
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
                        Model::saveLog(Yii::$app->user->identity->username, "Update eSK Approval with ID ".$model->id);
                
                        Yii::$app->session->setFlash('success', "Your esk approval successfully updated."); 
                        return $this->redirect(['esk-approval-master/index']); 
                    }else{
                        Yii::$app->session->setFlash('error', "Your esk approval was not updated.");
                        return $this->redirect(['esk-approval-master/index']); 
                    }
                } catch (Exception $e) {
                    // penyimpanan gagal, rollback database transaction
                    $transaction->rollBack();
                    throw $e;
                }
            } else {
                return $this->render('update', [
                    'model' => $model,
                    'details' => $details,
                    'error' => 'valid1: '.print_r($valid1,true).' - valid2: '.print_r($valid2,true),
                ]);
            }
        }

        //set array
        $model->directorate = explode(",",$model->directorate);
		$model->authority_area = explode(",",$model->authority_area);
		
        // render view
        return $this->render('update', [
            'model' => $model,
            'details' => (empty($details)) ? [new EskApprovalDetail] : $details,
        ]);
    }

    /**
     * Deletes an existing EskApprovalMaster model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $details = $model->details;

         // mulai database transaction
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // pertama, delete semua detail records
            foreach ($details as $detail) {
                if(!empty($detail->id)){
                    EskApprovalDetail::deleteAll(['id' => $detail->id]);
                }
            }

            // kemudian, delete master record
            if ($model->delete()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Approval with ID ".$id);
                
                Yii::$app->session->setFlash('success', "Your esk approval successfully deleted."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk approval was not deleted.");
            }
            // sukses, commit transaction
            $transaction->commit();
    
        } catch (Exception $e) {
            // gagal, rollback database transaction
            $transaction->rollBack();
        }

        return $this->redirect(['esk-approval-master/index']);
    }

    /**
     * Finds the EskApprovalMaster model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskApprovalMaster the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskApprovalMaster::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionEmplist($q = null, $id = null, $type = null) {
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
