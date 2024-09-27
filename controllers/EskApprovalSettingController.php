<?php

namespace esk\controllers;

use Yii;
use esk\models\EskApprovalSetting;
use esk\models\EskApprovalSettingSearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * EskApprovalSettingController implements the CRUD actions for EskApprovalSetting model.
 */
class EskApprovalSettingController extends Controller
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
     * Lists all EskApprovalSetting models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskApprovalSettingSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskApprovalSetting model.
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
     * Creates a new EskApprovalSetting model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskApprovalSetting();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Approval with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your esk approval successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk approval was not saved.");
            }
            return $this->redirect(['esk-approval-setting/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskApprovalSetting model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Approval with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your esk approval successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk approval was not updated.");
            }
            return $this->redirect(['esk-approval-setting/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskApprovalSetting model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Approval with ID ".$id);
            Yii::$app->session->setFlash('success', "Your esk approval successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk approval was not deleted.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the EskApprovalSetting model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskApprovalSetting the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskApprovalSetting::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionEmplist($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(["nik AS id, CONCAT(nama, ' (', title, ')') AS text"])
                ->from('employee')
                ->where(['like', 'nama', $q])
                ->orWhere(['like', 'title', $q])
                ->andWhere(['status' => 'AKTIF']);
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
