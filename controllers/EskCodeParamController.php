<?php

namespace esk\controllers;

use Yii;
use esk\models\EskCodeParam;
use esk\models\EskCodeParamSearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * EskCodeParamController implements the CRUD actions for EskCodeParam model.
 */
class EskCodeParamController extends Controller
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
     * Lists all EskCodeParam models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskCodeParamSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskCodeParam model.
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
     * Creates a new EskCodeParam model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskCodeParam();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Parameter with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your eSK Parameter successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your eSK Parameter was not saved.");
            }
            return $this->redirect(['esk-code-param/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);

    }

    /**
     * Updates an existing EskCodeParam model.
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
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Parameter with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your eSK Parameter successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your eSK Parameter was not updated.");
            }
            return $this->redirect(['esk-code-param/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskCodeParam model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Parameter with ID ".$id);
                
            Yii::$app->session->setFlash('success', "Your eSK Parameter successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your eSK Parameter was not deleted.");
        }
        return $this->redirect(['index']);
    }

    /**
     * Finds the EskCodeParam model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskCodeParam the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskCodeParam::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
