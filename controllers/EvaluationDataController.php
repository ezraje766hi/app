<?php

namespace esk\controllers;

use Yii;
use esk\models\EvaluationData;
use esk\models\EvaluationDataSearch;
use esk\models\BeritaAcara;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use common\components\Helpers;

/**
 * EvaluationDataController implements the CRUD actions for EvaluationData model.
 */
class EvaluationDataController extends Controller
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
     * Lists all EvaluationData models.
     * @return mixed
     */
    public function actionIndex($d=0)
    {
		set_time_limit(0);
		ini_set('memory_limit','512M');
        $searchModel = new EvaluationDataSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

		if($d)
		{
			$result = [];
			foreach($dataProvider->query->all() as $rows)
			{
				$tmp = [
					'NIK' => $rows->nik,
					'NAMA' => $rows->name,
					'POSISI SETELAH EVALUASI' => (empty($rows->positionRecommendation->nama)) ? NULL : $rows->positionRecommendation->nama,
					'UNIT KERJA' => (empty($rows->positionRecommendation->organization)) ? NULL : $rows->positionRecommendation->organization,
					'BP' => $rows->BP,
					'BI LAMA' => $rows->bi_before_eva,
					'BI BARU' => $rows->BI,
					'TANGGAL BERLAKU SK' => $rows->effectiveDate,
					'DPE' => $rows->dpe,
					'STATUS' => $rows->evaluationType,
					'REVIEWER_APPROVE_DATE' => $rows->reviewer_approved_appraisal,
					'HCM_APPROVE_DATE' => $rows->hcm_approved_appraisal,
				];
				
				$result[] = $tmp;
			}
			$filename = "EVALUASI_".date("YmdHis").".csv";
			Helpers::createCsv($filename,$result);
			exit;
		}
		
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single EvaluationData model.
     * @param string $nik
     * @param string $start_date_assigment
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($nik, $start_date_assigment)
    {
        return $this->render('view', [
            'model' => $this->findModel($nik, $start_date_assigment),
        ]);
    }

    /**
     * Creates a new EvaluationData model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new EvaluationData();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'nik' => $model->nik, 'start_date_assigment' => $model->start_date_assigment]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EvaluationData model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $nik
     * @param string $start_date_assigment
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($nik, $start_date_assigment)
    {
        $model = $this->findModel($nik, $start_date_assigment);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'nik' => $model->nik, 'start_date_assigment' => $model->start_date_assigment]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

  
    public function actionDelete($nik, $start_date_assigment)
    {
        $this->findModel($nik, $start_date_assigment)->delete();

        return $this->redirect(['index']);
    }
	
    protected function findModel($nik, $start_date_assigment)
    {
        if (($model = EvaluationData::findOne(['nik' => $nik, 'start_date_assigment' => $start_date_assigment])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
	
	public function actionPopulateDataOld()
	{
		$evaluation  = new EvaluationData;
		$evaluation->generateBeritaAcara;
	}
	
	public function actionPopulateData()
	{
		set_time_limit(0);
		ini_set('memory_limit', '5048M');
		
		$evaluation  = new EvaluationData;
		$evaluation->getGenerateBeritaAcara();
		
		echo "Success Build ESK";
		exit;
		// Yii::$app->session->setFlash('success', 'Success Build ESK');
		// return $this->redirect(['evaluation-data/index']);
		
	}
	
	public function actionPopulateDataApi()
	{
		set_time_limit(0);
		ini_set('memory_limit', '5048M');
		
		//EvaluationData::deleteAll();
		$evaluation  = new EvaluationData;
		$evaluation->populateDataFromApi;
		
		echo "Success Sych evaluation data to ESK Application";
		exit;
		// Yii::$app->session->setFlash('success', 'Success Sych evaluation data to ESK Application');
		// return $this->redirect(['evaluation-data/index']);
	}
}
