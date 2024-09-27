<?php

namespace esk\controllers;

use Yii;
use esk\models\EskMutation;
use esk\models\EskMutationSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use esk\models\Model;
use yii\filters\AccessControl;
/**
 * EskMutationController implements the CRUD actions for EskMutation model.
 */
class EskMutationController extends Controller
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
                        'actions' => ['login', 'logout', 'error'],
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
     * Lists all EskMutation models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskMutationSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskMutation model.
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
     * Creates a new EskMutation model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskMutation();

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();
            $origin_city = explode(";",$request['EskMutation']['code_city_origin']);
            $dest_city = explode(";",$request['EskMutation']['code_city_destination']);
            $model->code_city_origin = $origin_city[0];
            $model->desc_city_origin = $origin_city[1];
            $model->code_city_destination = $dest_city[0];
            $model->desc_city_destination = $dest_city[1];

            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Mutation Params with ID ".$model->id);
          
                Yii::$app->session->setFlash('success', "Your esk mutation params successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk mutation params was not saved.");
            }
            return $this->redirect(['esk-mutation/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskMutation model.
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
            if(strpos($request['EskMutation']['code_city_origin'], ";") !== false){
                $origin_city = explode(";",$request['EskMutation']['code_city_origin']);
                $model->code_city_origin = $origin_city[0];
                $model->desc_city_origin = $origin_city[1];
            }
            if(strpos($request['EskMutation']['code_city_destination'], ";") !== false){
                $dest_city = explode(";",$request['EskMutation']['code_city_destination']);    
                $model->code_city_destination = $dest_city[0];
                $model->desc_city_destination = $dest_city[1];
            }

            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Mutation Params with ID ".$model->id);
          
                Yii::$app->session->setFlash('success', "Your esk mutation params successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your esk mutation params was not updated.");
            }
            return $this->redirect(['esk-mutation/index']);
        }


        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskMutation model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Mutation Params with ID ".$id);
          
            Yii::$app->session->setFlash('success', "Your esk mutation params successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your esk mutation params was not deleted.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the EskMutation model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskMutation the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskMutation::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionCitylist($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(['DISTINCT(CONCAT(code,";",name)) AS id', 'name AS text'])
                ->from('city')
                ->where(['like', 'name', $q]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Position::find($id)->nama];
        }
        return $out;
    }
}
