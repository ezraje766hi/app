<?php

namespace esk\controllers;

use Yii;
use esk\models\EskDeliver;
use esk\models\EskDeliverSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use esk\models\Model;

/**
 * EskDeliverController implements the CRUD actions for EskDeliver model.
 */
class EskDeliverController extends Controller
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
     * Lists all EskDeliver models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskDeliverSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskDeliver model.
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
     * Creates a new EskDeliver model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskDeliver();

        if ($model->load(Yii::$app->request->post())) {
			$request = Yii::$app->request->post();
			
			$authority_array = $request['EskDeliver']['authority'];
            $authority = implode(",",$authority_array);
            $model->authority = $authority;
			
			$directorate_array = $request['EskDeliver']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Deliver with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your eSK Deliver successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your eSK Deliver was not saved.");
            }
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskDeliver model.
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
			
			$authority_array = $request['EskDeliver']['authority'];
            $authority = implode(",",$authority_array);
            $model->authority = $authority;
			
			$directorate_array = $request['EskDeliver']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Deliver with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your esk deliver user successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk deliver user was not updated.");
            }
            return $this->redirect(['index']);
        }
		
		//set array
        $model->authority = explode(",",$model->authority);
		$model->directorate = explode(",",$model->directorate);
		
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskDeliver model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Deliver with ID ".$id);
                
            Yii::$app->session->setFlash('success', "Your esk deliver user successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk deliver user was not deleted.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the EskDeliver model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskDeliver the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskDeliver::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
