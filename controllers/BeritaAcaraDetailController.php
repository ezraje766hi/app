<?php

namespace esk\controllers;

use Yii;
use esk\models\GenerateEsk;
use esk\models\Employee;
use esk\models\Position;
use esk\models\BeritaAcaraDetailSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * BeritaAcaraDetailController implements the CRUD actions for GenerateEsk model.
 */
class BeritaAcaraDetailController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all GenerateEsk models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new BeritaAcaraDetailSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single GenerateEsk model.
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
     * Creates a new GenerateEsk model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id)
    {
        $model = new GenerateEsk();
		$model->berita_acara_id = $id;
		
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['berita-acara/view', 'id' => $model->berita_acara_id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing GenerateEsk model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['berita-acara/view', 'id' => $model->berita_acara_id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing GenerateEsk model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * Finds the GenerateEsk model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return GenerateEsk the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = GenerateEsk::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
	
	public function actionEmployeelist($q = null, $id = 0) {
		Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		
		$out = ['results' => ['id' => '', 'text' => '']];
		
		if (!is_null($q)) {		
			$data = Employee::find()->where(['status' => 'AKTIF'])->andWhere(['like', 'nama', $q])->limit(100)->all();
			
			foreach($data as $rows)
				$tmp[] = ["id" => $rows->person_id, "text" => "(". $rows->nik .") " .$rows->nama];
			
			$out['results'] = (empty($tmp)) ? ['id' => '', 'text' => ''] : $tmp;
		}elseif ($id > 0) {
			$out['results'] = ['id' => $id, 'text' => Employee::findOne(['person_id' => $id])->nama];
		}
		
		return $out;
	}
	
	public function actionPositionlist($q = null, $id = 0) {
		Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		
		$out = ['results' => ['id' => '', 'text' => '']];
		
		if (!is_null($q)) {		
			$data = Position::find()->where(['status' => 1])->andWhere(['>=', 'job_id', 2000])->andWhere(['like', 'nama', $q])->limit(100)->all();
			
			foreach($data as $rows)
				$tmp[] = ["id" => $rows->id, "text" =>$rows->nama];
			
			$out['results'] = (empty($tmp)) ? ['id' => '', 'text' => ''] : $tmp;
		}elseif ($id > 0) {
			$out['results'] = ['id' => $id, 'text' => Position::findOne(['id' => $id])->nama];
		}
		
		return $out;
	}
}
