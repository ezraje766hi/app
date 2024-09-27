<?php

namespace esk\controllers;

use Yii;
use esk\models\EskContent;
use esk\models\EskContentSearch;
use esk\models\Model;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * EskContentController implements the CRUD actions for EskContent model.
 */
class EskContentController extends Controller
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
     * Lists all EskContent models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskContentSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskContent model.
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
     * Creates a new EskContent model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {   
        $model = new EskContent();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new esk Content with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your esk content successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk content was not saved.");
            }
            return $this->redirect(['esk-content/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionCopy($id)
    {   
        $model = new EskContent();
        $model2 = EskContent::findOne($id);

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new esk Content with ID ".$model->id);
                Yii::$app->session->setFlash('success', "Your esk content successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk content was not saved.");
            }
            return $this->redirect(['esk-content/index']);
        }

        return $this->render('create', [
            'model' => $model2,
        ]);
    }

    /**
     * Updates an existing EskContent model.
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
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Content with ID ".$model->id);
                
                Yii::$app->session->setFlash('success', "Your esk content successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk content was not updated.");
            }
            return $this->redirect(['esk-content/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskContent model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Content with ID ".$id);
                
            Yii::$app->session->setFlash('success', "Your esk content successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk content was not deleted.");
        }
        return $this->redirect(['index']);
    }

    /**
     * Finds the EskContent model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskContent the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskContent::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionLists($id){
        $countContent = EskContent::find()
            ->where(['id_section'=>$id])
            ->count();
        $contents = EskContent::find()
            ->where(['id_section'=>$id])
            ->all();
        if($countContent > 0)
        {
            foreach ($contents as $content) {
                if(strlen($content->description) < 140){
                    echo "<option value='" .$content->id. "'>".$content->title."</option>";
                }else{
                    //echo "<option value='" .$content->id. "'>".substr($content->description,0,140)."...</option>";
                    echo "<option value='" .$content->id. "'>".$content->title."</option>";
                }
            }
			exit;
        }
        else
        {
            echo "<option>-</option>";
        }
    }
}
