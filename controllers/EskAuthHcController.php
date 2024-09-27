<?php

namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use esk\models\EskAuthHc;
use esk\models\EskAuthHcSearch;

class EskAuthHcController extends Controller
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
        $searchModel = new EskAuthHcSearch();
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
        $model = new EskAuthHc();

        if ($model->load(Yii::$app->request->post())) {
            
			if(!empty($model->old_directorate))
				$model->old_directorate = trim(implode(";",$model->old_directorate));
			
			if(!empty($model->new_directorate))
				$model->new_directorate = trim(implode(";",$model->new_directorate));
			
			if(!empty($model->old_area))
				$model->old_area = trim(implode(";",$model->old_area));
			
			if(!empty($model->new_area))
				$model->new_area = trim(implode(";",$model->new_area));
			
			$model->created_at = date('Y-m-d H:i:s');
			$model->updated_at = date('Y-m-d H:i:s');
			
			if($model->save())
				return $this->redirect(array('esk-auth-hc/index'));
        }

        return $this->renderAjax('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
		
		$model->old_directorate = $model->getArrValue($model->old_directorate);
		$model->new_directorate = $model->getArrValue($model->new_directorate);
		$model->old_area 		= $model->getArrValue($model->old_area);
		$model->new_area 		= $model->getArrValue($model->new_area);
			
        if ($model->load(Yii::$app->request->post())) {
			
			if(!empty($model->old_directorate))
				$model->old_directorate = trim(implode(";",$model->old_directorate));
			
			if(!empty($model->new_directorate))
				$model->new_directorate = trim(implode(";",$model->new_directorate));
			
			if(!empty($model->old_area))
				$model->old_area = trim(implode(";",$model->old_area));
			
			if(!empty($model->new_area))
				$model->new_area = trim(implode(";",$model->new_area));
			
			$model->updated_at = date('Y-m-d H:i:s');
			
            if($model->save())
				return $this->redirect(array('esk-auth-hc/index'));
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
        if (($model = EskAuthHc::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
