<?php

namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use esk\models\Employee;
use esk\models\EskListHcbp;
use esk\models\EskListHcbpSearch;

class EskListHcbpController extends Controller
{

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

    public function actionIndex()
    {
        $searchModel = new EskListHcbpSearch();
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
        $model 		= new EskListHcbp();

		if ($model->load(Yii::$app->request->post())) {
			
			$dataEmployee		= Employee::findOne(['nik' => $model->nik]);
			$model->nama 		= $dataEmployee->nama;
			
			$dataAtasan			= Employee::findOne(['nik' => $model->nik_atasan]);
			$model->nama_atasan = $dataAtasan->nama;
			
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
			
			$dataAtasan			= Employee::findOne(['nik' => $model->nik_atasan]);
			$model->nama_atasan = $dataAtasan->nama;
			
            if($model->save()) {
				return $this->redirect(['index']);
			}
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = EskListHcbp::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
