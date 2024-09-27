<?php

namespace esk\controllers;

use Yii;
use esk\models\EskDecreeBy;
use esk\models\EskDecreeBySearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * EskDecreeByController implements the CRUD actions for EskDecreeBy model.
 */
class EskDecreeByController extends Controller
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
     * Lists all EskDecreeBy models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskDecreeBySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskDecreeBy model.
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
     * Creates a new EskDecreeBy model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskDecreeBy();

        if ($model->load(Yii::$app->request->post())) {
			
			$request = Yii::$app->request->post();
            $directorate_array = $request['EskDecreeBy']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Decree By data with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your eSK Decree By data successfully created."); 
            } else {
                $error = implode(",",$model->getErrorSummary(true));
                Yii::$app->session->setFlash('error', "Your eSK Decree By data was not saved, because ".$error);
            }
            return $this->redirect(['esk-decree-by/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskDecreeBy model.
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
            $directorate_array = $request['EskDecreeBy']['directorate'];
            $directorate = implode(",",$directorate_array);
            $model->directorate = $directorate;
			
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Decree By data with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your eSK Decree By data successfully updated."); 
            } else {
                $error = implode(",",$model->getErrorSummary(true));
                Yii::$app->session->setFlash('error', "Your eSK Decree By data was not updated, because ".$error);
            }
            return $this->redirect(['esk-decree-by/index']);
        }
		
		//set array
        $model->directorate = explode(",",$model->directorate);
		
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskDecreeBy model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Decree By data with ID ".$id);
                
            Yii::$app->session->setFlash('success', "Your eSK Decree By data successfully deleted."); 
        } else {
            $error = implode(",",$model->getErrorSummary(true));
            Yii::$app->session->setFlash('error', "Your eSK Decree By data was not deleted, because ".$error);
        }
        return $this->redirect(['index']);
    }

    /**
     * Finds the EskDecreeBy model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskDecreeBy the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskDecreeBy::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
