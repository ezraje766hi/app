<?php

namespace esk\controllers;

use Yii;
use esk\models\EskTemplateType;
use esk\models\EskTemplateTypeSearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * EskTemplateTypeController implements the CRUD actions for EskTemplateType model.
 */
class EskTemplateTypeController extends Controller
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
     * Lists all EskTemplateType models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskTemplateTypeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskTemplateType model.
     * @param string $id
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
     * Creates a new EskTemplateType model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskTemplateType();

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();
            $model->id = $request['EskTemplateType']['type'];
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new esk template type with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your esk template type successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk template type was not saved.");
            }
            return $this->redirect(['esk-template-type/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskTemplateType model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();
            $model->id = $request['EskTemplateType']['type'];
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK template type with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your esk template type successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk template type was not updated.");
            }
            return $this->redirect(['esk-template-type/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskTemplateType model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK template type with ID ".$id);
                
            Yii::$app->session->setFlash('success', "Your esk template type successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk template type was not deleted.");
        }
        return $this->redirect(['index']);
    }

    /**
     * Finds the EskTemplateType model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return EskTemplateType the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskTemplateType::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
