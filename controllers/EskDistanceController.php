<?php

namespace esk\controllers;

use Yii;
use esk\models\EskDistance;
use esk\models\EskDistanceSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use esk\models\Model;
use yii\filters\AccessControl;
/**
 * EskDistanceController implements the CRUD actions for EskDistance model.
 */
class EskDistanceController extends Controller
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
     * Lists all EskDistance models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EskDistanceSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EskDistance model.
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
     * Creates a new EskDistance model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EskDistance();

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();
            $origin_city = explode(";",$request['EskDistance']['kota_asal']);
            $dest_city = explode(";",$request['EskDistance']['kota_tujuan']);
            $model->kota_asal = ucwords(strtolower($origin_city[1]));
            $model->kota_tujuan = ucwords(strtolower($dest_city[1]));

            if ($model->save()) {
                $model2 = new EskDistance();
                $model2->kota_asal = $model->kota_tujuan;
                $model2->kota_tujuan = $model->kota_asal;
                $model2->lokasi_tujuan = $model->lokasi_tujuan;
                $model2->jarak = $model->jarak;
                $model2->save();

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new eSK Distance Params with ID ".$model->id);
          
                Yii::$app->session->setFlash('success', "Your eSK Distance params successfully created."); 
            } else {
                Yii::$app->session->setFlash('error', "Your eSK Distance params was not saved.");
            }
            return $this->redirect(['esk-distance/index']);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskDistance model.
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
            if(strpos($request['EskDistance']['kota_asal'], ";") !== false){
                $origin_city = explode(";",$request['EskDistance']['kota_asal']);
                $model->kota_asal = ucwords(strtolower($origin_city[1]));
            }
            if(strpos($request['EskDistance']['kota_tujuan'], ";") !== false){
                $dest_city = explode(";",$request['EskDistance']['kota_tujuan']);    
                $model->kota_tujuan = ucwords(strtolower($dest_city[1]));
            }

            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update eSK Distance Params with ID ".$model->id);
          
                Yii::$app->session->setFlash('success', "Your eSK Distance params successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "Your eSK Distance params was not updated.");
            }
            return $this->redirect(['esk-distance/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskDistance model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Distance Params with ID ".$id);
          
            Yii::$app->session->setFlash('success', "Your eSK Distance params successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "Your eSK Distance params was not deleted.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the EskDistance model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskDistance the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskDistance::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionCitylists($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(['DISTINCT(kota_tujuan) AS id', 'kota_tujuan AS text'])
                ->from('esk_jarak')
                ->where(['like', 'kota_tujuan', $q]);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => EskDistance::find($id)->nama];
        }
        return $out;
    }
}
