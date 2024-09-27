<?php

namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;

use esk\models\Employee;
use esk\models\EmployeeSearch;

class EmployeeController extends Controller
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
        $searchModel = new EmployeeSearch();
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
        $model = new Employee();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->person_id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->person_id]);
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
	
    public function actionUpload()
	{
		set_time_limit(0);
		ini_set('memory_limit', '8048M');
		
		$model = new Employee();
		
		$errors = [];
		if ($model->load(Yii::$app->request->post()))
		{
			if($file = UploadedFile::getInstance($model, 'upload_file'))
			{
				$handle = fopen($file->tempName, "r");
				$data   = fgetcsv($handle, 10000, ";");
				$formatValid = $model->validateHeaderUpload($data);
				
				if($formatValid)
				{
					$i = 0;
					while (($data = fgetcsv($handle, 10000, ";")) !== FALSE) {
						$i++;
						
						if(!empty($data[Employee::UPLOAD_MAX_SEGMENT]))
						{
							$model 	= Employee::find()->where(['person_id' => $data[0]])->one();
							if(empty($model)) {
								$model = new Employee();
							}
							
							$model->person_id = $data[0];
							$model->no_ktp 	  = $data[1];
							$model->alamat 	  = $data[2];
							$model->no_npwp   = $data[3];
							$model->tunjangan = $data[4];
							$model->golongan_darah = $data[5];
							$model->nama_ibu  = $data[6];
							$model->suku      = $data[7];
							$model->nik       = $data[8];
							$model->nama      = $data[9];
							$model->title     = $data[10];
							$model->bp        = $data[11];
							$model->kota_lahir= $data[12];
							$model->bi 		  = $data[13];
							$model->tanggal_masuk = date('Y-m-d',strtotime($data[14]));
							$model->employee_category = $data[15];
							$model->organization = $data[16];
							$model->job = $data[17];
							$model->job_category = $data[18];
							$model->band = $data[19];
							$model->location = $data[20];
							$model->kota = $data[21];
							$model->no_hp = $data[22];
							$model->email = $data[23];
							$model->gender = $data[24];
							$model->status_pernikahan = $data[25];
							$model->agama = $data[26];
							$model->tgl_lahir = $data[27];
							$model->start_date_assignment = date('Y-m-d',strtotime($data[28]));
							$model->admins = $data[29];
							$model->nik_atasan = $data[30];
							$model->nama_atasan = $data[31];
							$model->section = $data[32];
							$model->department = $data[33];
							$model->division = $data[34];
							$model->bgroup = $data[35];
							$model->egroup = $data[36];
							$model->directorate = $data[37];
							$model->area = $data[38];
							$model->tgl_masuk = date('Y-m-d',strtotime($data[39]));
							$model->status = $data[40];
							$model->edu_lvl = $data[41];
							$model->edu_institution = $data[42];
							$model->edu_faculty = $data[43];
							$model->edu_major = $data[44];
							$model->salary = $data[45];
							$model->structural = $data[46];
							$model->functional = $data[47];
							$model->kode_kota = $data[48];
							$model->dpe = $data[49];
							$model->position_id = $data[50];
							$model->job_id = $data[51];
							$model->homebase = $data[52];
							
							$model->save();
							
							//var_dump($model->getErrors(),$model->band);exit;
						} else {
							$errors[$i] = [
								[
									'Data not complete please check your data',
								]
							];
						}
					}
					
					if(empty($errors))
					{	
						Yii::$app->session->setFlash('success', "Data Uploaded Successfully.");
						return $this->redirect(['index']);
					}
					
				} else {
					$errors[] = [
						[
							'Format data not valid',
						]
					];
				}
			}  else {
				$model->addError('upload_file', 'Silakan pilih file terlebih dahulu');
			}
			
			if(empty($errors))
				return $this->redirect(['index']);
		}
		
		return $this->render('upload',compact('model', 'errors'));
	}
	
    protected function findModel($id)
    {
        if (($model = Employee::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
