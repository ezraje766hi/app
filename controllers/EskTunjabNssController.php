<?php

namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use esk\models\EskTunjabNss;
use esk\models\EskTunjabNssSearch;

class EskTunjabNssController extends Controller
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
                        'roles' => ['sysadmin'],
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
        $searchModel = new EskTunjabNssSearch();
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
        $model 		= new EskTunjabNss();
		$employee 	= Yii::$app->user->identity->employee;
		
        if ($model->load(Yii::$app->request->post())) {
			
			$model->created_by	= $employee->person_id;
			$model->updated_by	= $employee->person_id;
			$model->created_at	= date('Y-m-d H:i:s');
			$model->updated_at 	= date('Y-m-d H:i:s');
			
			if($model->save()) {
				return $this->redirect(array('esk-tunjab-nss/index'));
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
            
			$model->created_by	= $employee->person_id;
			$model->updated_by	= $employee->person_id;
			$model->created_at	= date('Y-m-d H:i:s');
			$model->updated_at 	= date('Y-m-d H:i:s');
			
			if($model->save()) {
				return $this->redirect(array('esk-tunjab-nss/index'));
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
        if (($model = EskTunjabNss::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
