<?php

namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\components\Helpers;
use yii\filters\AccessControl;
use esk\models\EskDataEvaluationFailed;
use esk\models\EskDataEvaluationFailedSearch;

class EskDataEvaluationFailedController extends Controller
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
        $searchModel = new EskDataEvaluationFailedSearch();
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
        $model = new EskDataEvaluationFailed();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
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
	
	public function actionSync()
	{
		set_time_limit(0);
		ini_set('memory_limit','512M');
		
		$data_download 	= json_decode(file_get_contents('https://hcm.telkomsel.co.id/apps/hcis/get-data-esk-failed'), true);
		
		foreach($data_download as $rows)
		{
			$rows 			= (object)$rows;
			$data 			= EskDataEvaluationFailed::findOne(['nik' => $rows->nik, 'start_date_assigment' => $rows->start_date_assigment]);
			
			if(empty($data)){
				$data 			= new EskDataEvaluationFailed;
			
				$data->evaluation_type = $rows->evaluation_type;
				$data->document_no = $rows->document_no;
				$data->last_document_no = $rows->last_document_no;
				$data->nik = $rows->nik;
				$data->name = $rows->name;
				$data->email = $rows->email;
				$data->position = $rows->position;
				$data->section = $rows->section;
				$data->department = $rows->department;
				$data->division = $rows->division;
				$data->group_employee = $rows->group_employee;
				$data->directorate = $rows->directorate;
				$data->area = $rows->area;
				$data->BI = $rows->BI;
				$data->BP = $rows->BP;
				$data->BI_old = $rows->bi;
				$data->BP_old = $rows->bp;
				$data->bi_before_eva = $rows->bi;
				$data->bp_before_eva = $rows->bp;
				$data->location = $rows->location;
				$data->salary = $rows->salary;
				$data->tunjangan = $rows->tunjangan;
				$data->tunjangan_jabatan = $rows->tunjangan_jabatan;
				$data->evaluation_date = $rows->evaluation_date;
				$data->start_date_assigment = $rows->start_date_assigment;
				$data->finish_date_assigment = $rows->finish_date_assigment;
				$data->assesor_nik = $rows->assesor_nik;
				$data->assesor_name = $rows->assesor_name;
				$data->assesor_email = $rows->assesor_email;
				$data->assesor_position = $rows->assesor_position;
				$data->assesor_matrix_nik = $rows->assesor_matrix_nik;
				$data->assesor_matrix_name = $rows->assesor_matrix_name;
				$data->assesor_matrix_email = $rows->assesor_matrix_email;
				$data->assesor_matrix_position = $rows->assesor_matrix_position;
				$data->assesor_appraisal_nik = $rows->assesor_appraisal_nik;
				$data->assesor_appraisal_name = $rows->assesor_appraisal_name;
				$data->assesor_appraisal_email = $rows->assesor_appraisal_email;
				$data->assesor_appraisal_position = $rows->assesor_appraisal_position;
				$data->flag_assesor_position = $rows->flag_assesor_position;
				$data->reviewer_name = $rows->reviewer_name;
				$data->reviewer_email = $rows->reviewer_email;
				$data->reviewer_appraisal_name = $rows->reviewer_appraisal_name;
				$data->reviewer_appraisal_email = $rows->reviewer_appraisal_email;
				$data->status = $rows->status;
				$data->tracking = $rows->tracking;
				$data->flag_process_employee_target = $rows->flag_process_employee_target;
				$data->flag_process_employee_appraisal = $rows->flag_process_employee_appraisal;
				$data->flag_process_employee_assesor = $rows->flag_process_employee_assesor;
				$data->flag_process_appraisal_assesor = $rows->flag_process_appraisal_assesor;
				$data->evaluation_step = $rows->evaluation_step;
				$data->flag_procedure = $rows->flag_procedure;
				$data->count_procedure = $rows->count_procedure;
				$data->appraisal_result = $rows->appraisal_result;
				$data->extend_date = $rows->extend_date;
				$data->flag_extend = $rows->flag_extend;
				$data->appraisal_reason = $rows->appraisal_reason;
				$data->new_position = $rows->new_position;
				$data->new_location = $rows->new_location;
				$data->verification_date = $rows->verification_date;
				$data->verification_by = $rows->verification_by;
				$data->flag_sk_create = $rows->flag_sk_create;
				$data->assesor_create_target = $rows->assesor_create_target;
				$data->employee_ack_target = $rows->employee_ack_target;
				$data->reviewer_approved_target = $rows->reviewer_approved_target;
				$data->reviewer_rejected_target = $rows->reviewer_rejected_target;
				$data->assesor_correction_target = $rows->assesor_correction_target;
				$data->assesor_create_appraisal = $rows->assesor_create_appraisal;
				$data->employee_ack_appraisal = $rows->employee_ack_appraisal;
				$data->reviewer_approved_appraisal = $rows->reviewer_approved_appraisal;
				$data->reviewer_rejected_appraisal = $rows->reviewer_rejected_appraisal;
				$data->assesor_correction_appraisal = $rows->assesor_correction_appraisal;
				$data->hcm_approved_appraisal = $rows->hcm_approved_appraisal;
				$data->created_at = $rows->created_at;
				$data->updated_at = $rows->updated_at;
				$data->old_flag = $rows->old_flag;
				$data->position_id = $rows->position_id;
					
				$data->save();
			}
		}
		
		exit;

	}
	
	public function actionDownload()
	{
		set_time_limit(0);
		ini_set('memory_limit','512M');
		
		$searchModel 	= new EskDataEvaluationFailedSearch();
        $dataProvider 	= $searchModel->search(Yii::$app->request->queryParams);
		$result 		= [];
		
		foreach($dataProvider->query->all() as $rows)
		{
			$tmp = [
				'NIK' => $rows->nik,
				'NAMA' => $rows->name,
				'BP' => $rows->BP,
				'BI LAMA' => $rows->bi_before_eva,
				'BI BARU' => $rows->BI,
				'STATUS' => $rows->tracking,
				'TYPE'	=> $rows->evaluation_type,
				'REVIEWER_APPROVE_DATE' => $rows->reviewer_approved_appraisal,
				'HCM_APPROVE_DATE' => $rows->hcm_approved_appraisal,
				'EXTEND_DATE' => $rows->extend_date,
				'REASON' => $rows->appraisal_reason,
				'VERIFICATION_DATE' => $rows->verification_date,
				'VERIFICATION_BY' => $rows->verification_by,
			];
			
			$result[] = $tmp;
		}
		$filename = "EVALUASI_FAILED".date("YmdHis").".csv";
		Helpers::createCsv($filename,$result);
		exit;
			
	}
	
    protected function findModel($id)
    {
        if (($model = EskDataEvaluationFailed::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
