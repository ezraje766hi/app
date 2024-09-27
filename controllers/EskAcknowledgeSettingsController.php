<?php

namespace esk\controllers;

use Yii;
use esk\models\EskAcknowledgeSettings;
use esk\models\EskAcknowledgeSettingsSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use esk\models\Model;

/**
 * EskAcknowledgeSettingsController implements the CRUD actions for EskAcknowledgeSettings model.
 */
class EskAcknowledgeSettingsController extends Controller
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
     * Lists all EskAcknowledgeSettings models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskAcknowledgeSettingsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskAcknowledgeSettings model.
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
     * Creates a new EskAcknowledgeSettings model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskAcknowledgeSettings();

        if ($model->load(Yii::$app->request->post())) {
			$request = Yii::$app->request->post();
			
			$tipe_array = $request['EskAcknowledgeSettings']['tipe'];
            $tipe = implode(",",$tipe_array);
            $model->tipe = $tipe;
			
			$authority_area_array = $request['EskAcknowledgeSettings']['authority_area'];
            $authority_area = implode(",",$authority_area_array);
            $model->authority_area = $authority_area;
			
			$directorate_array = $request['EskAcknowledgeSettings']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Acknowledge with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your eSK Acknowledge successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your eSK Acknowledge was not saved.");
            }
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskAcknowledgeSettings model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
			$request = Yii::$app->request->post();
			
			$tipe_array = $request['EskAcknowledgeSettings']['tipe'];
            $tipe = implode(",",$tipe_array);
            $model->tipe = $tipe;
			
			$authority_area_array = $request['EskAcknowledgeSettings']['authority_area'];
            $authority_area = implode(",",$authority_area_array);
            $model->authority_area = $authority_area;
			
			$directorate_array = $request['EskAcknowledgeSettings']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Acknowledge with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your esk category successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk category was not updated.");
            }
            return $this->redirect(['index']);
        }
		
		//set array
        $model->tipe = explode(",",$model->tipe);
		$model->authority_area = explode(",",$model->authority_area);
		$model->directorate = explode(",",$model->directorate);
		
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskAcknowledgeSettings model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Acknowledge with ID ".$id);
                
            Yii::$app->session->setFlash('success', "Your esk category successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk category was not deleted.");
        }

        return $this->redirect(['index']);
    }

    public function actionDeleteAll($id)
    {
        $ids = explode(",",$id);
        $count_all = 0;
        $count_success = 0;
        $count_failed = 0;

        foreach($ids as $id){
            if ($this->findModel($id)->delete()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Acknowledge with ID ".$id);
                    
                $count_success = $count_success + 1;
            } else {
                $count_failed = $count_failed + 1;
            }

            $count_all = $count_all + 1;
        }

        Yii::$app->session->setFlash('info', 'Successfully deleted ' . $count_all . ' data with Success ' . $count_success . ' data and Failed ' . $count_failed . ' data '); 
   
        return $this->redirect(['index']);
    }

    /**
     * Finds the EskAcknowledgeSettings model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskAcknowledgeSettings the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskAcknowledgeSettings::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
