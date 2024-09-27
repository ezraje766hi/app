<?php

namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use esk\models\Employee;
use esk\models\EskSo;
use esk\models\EskSoSearch;

class EskSoController extends Controller
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

    public function actionIndex()
    {
        $searchModel = new EskSoSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }
	
    public function actionCreate()
    {
        $model = new EskSo();

        if ($model->load(Yii::$app->request->post())) {
			
			$dataEmployee		= Employee::findOne(['nik' => $model->nik]);
			$model->nama 		= $dataEmployee->nama;
			
			$dataAtasan			= Employee::findOne(['nik' => $model->so_nik]);
			$model->so_name 	= $dataAtasan->nama;
			
			if($model->save()) {
				return $this->redirect(['index']);
			}
		}

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
			
			$dataEmployee		= Employee::findOne(['nik' => $model->nik]);
			$model->nama 		= $dataEmployee->nama;
			
			$dataAtasan			= Employee::findOne(['nik' => $model->so_nik]);
			$model->so_name 	= $dataAtasan->nama;
			
            if($model->save()) {
				return $this->redirect(['index']);
			}
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskSo model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the EskSo model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskSo the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = EskSo::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
