<?php

namespace esk\controllers;

use Yii;
use esk\models\EskCategory;
use esk\models\EskCategorySearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * EskCategoryController implements the CRUD actions for EskCategory model.
 */
class EskCategoryController extends Controller
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
     * Lists all EskCategory models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskCategorySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskCategory model.
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
     * Creates a new EskCategory model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskCategory();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new esk Category with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your esk category successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk category was not saved.");
            }
            return $this->redirect(['esk-category/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskCategory model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Category with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your esk category successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk category was not updated.");
            }
            return $this->redirect(['esk-category/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskCategory model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Category with ID ".$id);
                
            Yii::$app->session->setFlash('success', "Your esk category successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk category was not deleted.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the EskCategory model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskCategory the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskCategory::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
