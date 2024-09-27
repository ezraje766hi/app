<?php

namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use yii\filters\AccessControl;

use common\components\BasicHelper;

use esk\models\Position;
use esk\models\PositionSearch;


class PositionController extends Controller
{
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
        $searchModel = new PositionSearch();
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
        $model = new Position();

        if ($model->load(Yii::$app->request->post())) {
			
			$model->job_id = 999999;
			$model->status = 1;
			
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
			
			
			if($model->save()) {
				return $this->redirect(['index']);
			}
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }
	
	public function actionUpload()
	{
		set_time_limit(0);
		ini_set('memory_limit', '8048M');
		
		$model = new Position();
		
		$errors = [];
		
		if ($model->load(Yii::$app->request->post()))
		{
			if($file = UploadedFile::getInstance($model, 'upload_file'))
			{
				$handle = fopen($file->tempName, "r");
				$data = fgetcsv($handle, 10000, ";");
				$formatValid = $model->validateHeaderUpload($data);
				
				if($formatValid)
				{
					$i = 0;
					while (($data = fgetcsv($handle, 10000, ";")) !== FALSE) {
						$i++;
						
						$isExist = Position::findOne(['status' => 1,'nama' => $data[0], 'organization' => $data[3], 'desc_city' => $data[2], 'directorate' => $data[4] , 'area' => $data[6]]);
						
						if(empty($isExist)) {					
							
							$model = new Position();
							$model->id 	= 0;
							$model->job_id 	= 999999;
							$model->status 	= 1;
							
							$model->nama = $data[0];
							$model->bp = $data[1];
							$model->desc_city = $data[2];
							$model->organization = $data[3];
							$model->directorate = $data[4];
							
							if($data[5] == "S") {
								$model->structural = "Y";
							} elseif($data[5] == "F") {
								$model->functional = "Y";
							}
							
							$model->area = $data[6];
							$model->admins = $data[7];
							$model->band = (int)substr($model->bp,0,1);
							$model->created_at = date('Y-m-d H:i:s');
							$model->updated_at = date('Y-m-d H:i:s');
							$model->save();
							
						} else {
							$model = $isExist;
							$model->status 	= 1;
							
							$model->nama = $data[0];
							$model->bp = $data[1];
							$model->desc_city = $data[2];
							$model->organization = $data[3];
							$model->directorate = $data[4];
							
							if($data[5] == "S") {
								$model->structural = "Y";
							} elseif($data[5] == "F") {
								$model->functional = "Y";
							}
							
							$model->area = $data[6];
							$model->admins = $data[7];
							$model->band = (int)substr($model->bp,0,1);
							$model->save();
						}
					}
					
					if(empty($errors))
					{	
						Yii::$app->session->setFlash('success', "Data Uploaded Successfully.");
						return $this->redirect(['index']);
					}
				}
			}
		}
		
		return $this->render('upload',compact('model', 'errors'));
	}
	
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = Position::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
	
	public function actionDownload()
	{
		set_time_limit(0);
		ini_set('memory_limit', '8048M');


			$sql = "
			SELECT
				*
			FROM
				position
			";

			$conn = Yii::$app->db;
			$result = $conn->createCommand($sql)
				->queryAll();


		BasicHelper::createCsv("REPORT_POSITION_" . date("Ymd_His") . ".csv", $result);
	}
}
