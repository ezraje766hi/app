<?php

namespace esk\controllers;

use Yii;
use esk\models\EskAuthorityMaster;
use esk\models\EskAuthorityMasterSearch;
use esk\models\EskAuthorityDetail;
use esk\models\EskAuthorityDetailSearch;
use esk\models\Employee;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\filters\AccessControl;

/**
 * EskAuthorityMasterController implements the CRUD actions for EskAuthorityMaster model.
 */
class EskAuthorityMasterController extends Controller
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
                        'roles' => ['sysadmin','hc_staffing'],
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
     * Lists all EskAuthorityMaster models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskAuthorityMasterSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskAuthorityMaster model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $searchModel = new EskAuthorityDetailSearch();
        $details = $searchModel->findDetails($id);

        return $this->render('view', [
            'model' => $model,
            'details' => $details,
        ]);
    }

    /**
     * Creates a new EskAuthorityMaster model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskAuthorityMaster();
        $details = [new EskAuthorityDetail];

        //proses post variabel
        if ($model->load(Yii::$app->request->post())) {
            $details = Model::createMultiple(EskAuthorityDetail::classname());
            Model::loadMultiple($details, Yii::$app->request->post());

            // assign default transaction_id
            foreach ($details as $detail) {
                $detail->id_master = 0;
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
                            $detail->id_master = $model->id;
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
                        Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Authority with ID ".$model->id);
                
                        Yii::$app->session->setFlash('success', "Your esk authority successfully created."); 
                        return $this->redirect(['esk-authority-master/index']); 
                    } else {
                        Yii::$app->session->setFlash('error', "Your esk authority was not saved.");
                        return $this->redirect(['esk-authority-master/index']); 
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
     * Updates an existing EskAuthorityMaster model.
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
            //data template detail
            $oldIDs = ArrayHelper::map($details, 'id', 'id');
            $details = Model::createMultiple(EskAuthorityDetail::classname(), $details);
            Model::loadMultiple($details, Yii::$app->request->post());
            $deletedIDs = array_diff($oldIDs, array_filter(ArrayHelper::map($details, 'id', 'id')));

            // assign default transaction_id
            foreach ($details as $detail) {
                $detail->id_master= $model->id;
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
                            EskAuthorityDetail::deleteAll(['id' => $deletedIDs]);
                        }

                        // kemudian, simpan details record
                        foreach ($details as $detail) {
                            $detail->id_master = $model->id;
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
                        Model::saveLog(Yii::$app->user->identity->username, "Update eSK Authority with ID ".$model->id);
                
                        Yii::$app->session->setFlash('success', "Your esk authority successfully updated."); 
                        return $this->redirect(['esk-authority-master/index']); 
                    }else{
                        Yii::$app->session->setFlash('error', "Your esk authority was not updated.");
                        return $this->redirect(['esk-authority-master/index']); 
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

        // render view
        return $this->render('update', [
            'model' => $model,
            'details' => (empty($details)) ? [new EskAuthorityDetail] : $details,
        ]);
    }

    /**
     * Deletes an existing EskAuthorityMaster model.
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
                    EskAuthorityDetail::deleteAll(['id' => $detail->id]);
                }
            }

            // kemudian, delete master record
            if ($model->delete()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Authority with ID ".$id);
                
                Yii::$app->session->setFlash('success', "Your esk authority successfully deleted."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk authority was not deleted.");
            }
            // sukses, commit transaction
            $transaction->commit();
    
        } catch (Exception $e) {
            // gagal, rollback database transaction
            $transaction->rollBack();
        }

        return $this->redirect(['esk-authority-master/index']);
    }

    /**
     * Finds the EskAuthorityMaster model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskAuthorityMaster the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskAuthorityMaster::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
