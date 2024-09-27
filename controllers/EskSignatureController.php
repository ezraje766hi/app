<?php

namespace esk\controllers;

use Yii;
use esk\models\EskSignature;
use esk\models\EskSignatureSearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use yii\filters\AccessControl;

/**
 * EskSignatureController implements the CRUD actions for EskSignature model.
 */
class EskSignatureController extends Controller
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
     * Lists all EskSignature models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskSignatureSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskSignature model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new EskSignature model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskSignature();
        if ($model->load(Yii::$app->request->post())) {
            $model->file_name = UploadedFile::getInstance($model,'file_name');
            if($model->file_name){
                $file = $model->file_name->name;
                if ($model->file_name->saveAs(Yii::getAlias('@esk').'/web/signature/'.$file)){
                    $model->file_name = $file;           
                }
            }
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Signature with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your esk signature successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk signature was not saved.");
            }
            return $this->redirect(['esk-signature/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskSignature model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {   
        $model = $this->findModel($id);
        $old_file = $model->file_name;

        if ($model->load(Yii::$app->request->post())) {
            $model->file_name = UploadedFile::getInstance($model,'file_name');
            if($model->file_name){
                $file = $model->file_name->name;
                if ($model->file_name->saveAs(Yii::getAlias('@esk').'/web/signature/'.$file)){
                    $model->file_name = $file;           
                }
            }
            if (empty($model->file_name)){
                    $model->file_name = $old_file;
            }

            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Signature with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your esk signature successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk signature was not updated.");
            }
            return $this->redirect(['esk-signature/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskSignature model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        @unlink(Yii::getAlias('@esk') . '/web/signature/' . $model->file_name);
        if ($model->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Signature with ID ".$model->id);
                
            Yii::$app->session->setFlash('success', "Your esk signature successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk signature was not deleted.");
        }
        return $this->redirect(['index']);
    }

    /**
     * Finds the EskSignature model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskSignature the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskSignature::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionViewGambar($nama){
        $file = Yii::getAlias('@esk/web/signature/' . $nama);
        return Yii::$app->response->sendFile($file, NULL, ['inline' => TRUE]);
    }
}
