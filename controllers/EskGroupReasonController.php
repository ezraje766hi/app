<?php

namespace esk\controllers;

use Yii;
use esk\models\EskGroupReasonData;
use esk\models\EskGroupReasonSearch;
use esk\models\BeritaAcara;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use common\components\Helpers;
use esk\models\EskGroupData;
use yii\data\ActiveDataProvider;

/**
 * EvaluationDataController implements the CRUD actions for EvaluationData model.
 */
class EskGroupReasonController extends Controller
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

    /**
     * Lists all EvaluationData models.
     * @return mixed
     */
    public function actionIndex($d=0)
    {
        set_time_limit(0);
        ini_set('memory_limit','512M');
        $searchModel = new EskGroupReasonSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        if($d)
        {
            $result = [];
            foreach($dataProvider->query->all() as $rows)
            {
                $tmp = [
                    'group' => $rows->group,
                    'reason' => $rows->reason,
                ];
                
                $result[] = $tmp;
            }
            exit;
        }
        
        $query = EskGroupData::find();

        $dataProviderG = new ActiveDataProvider([
            'query' => $query,
        ]);
        
        $query->orderBy('group Asc');

        
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'dataProviderGroup' => $dataProviderG,
        ]);
    }

    /**
     * Creates a new EvaluationData model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate_old()
    {
        $model = new EskGroupReasonData();
        if ($model->load(Yii::$app->request->post())) {
            
            $model2 = new EskGroupData();
            $model2->group = trim(ucwords($model->group));
            $model2->created_at = date("Y-m-d H:i:s");

            $cek_group_reason = EskGroupReasonData::find()->where(['group' => trim(ucwords($model->group)), 'reason' => trim(ucwords($model->reason))])->one();
            
            if(empty($cek_group_reason)){
                $cek_group = EskGroupData::find()->where(['group' => trim(ucwords($model->group))])->one();
                $cek_reason = EskGroupReasonData::find()->where(['reason' => trim(ucwords($model->reason))])->one();

                if(empty($cek_group)){
                    if($model2->save()) {
                        // $model2->id;
                        if(empty($cek_reason)){
                            $sql = 'INSERT INTO esk_group_reason (id_group, reason) VALUE ('.$model2->id.',"'.trim(ucwords($model->reason)).'")';
    
                            Yii::$app->db->createCommand($sql)->execute();
        
                            Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                            return $this->redirect(['index']);
                        }else{
                            $cek_reason1 = EskGroupReasonData::find()->where(['reason' => trim(ucwords($model->reason)), 'id_group' => Null])->one();
                            if(!empty($cek_reason1)){

                                Yii::$app->db->createCommand()
                                ->update('esk_group_reason', ['id_group' => $model2->id], 'id = '.$cek_reason1->id.'')
                                ->execute();
            
                                Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                                return $this->redirect(['index']);
                            }else{
                                Yii::$app->session->setFlash('info', "Group data created successfully ! But");
                                Yii::$app->session->setFlash('warning', "This reason data already has a group !");
                            }
                        }
                        
                    }
                }else{
                    $cek_reason2 = EskGroupReasonData::find()->where(['reason' => trim(ucwords($model->reason)), 'id_group' => Null])->one();
                    if(!empty($cek_reason2)){

                        Yii::$app->db->createCommand()
                        ->update('esk_group_reason', ['id_group' => $cek_group->id], 'id = '.$cek_reason2->id.'')
                        ->execute();
    
                        Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                        return $this->redirect(['index']);
                    }else{
                        Yii::$app->session->setFlash('warning', "This reason data already has a group !!");
                    }
                }
            }else{
                Yii::$app->session->setFlash('info', "Grouping reason data already exists");
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionCreate()
    {
        $model = new EskGroupReasonData();
        if ($model->load(Yii::$app->request->post())) {
            $model2 = new EskGroupData();
            $model2->group = trim(ucwords($model->group));
            $model2->created_at = date("Y-m-d H:i:s");

            $cek_group_reason = EskGroupReasonData::find()->where(['group' => trim(ucwords($model->group)), 'reason' => trim(ucwords($model->reason))])->one();
            
            if(empty($cek_group_reason))
            {
                $cek_group = EskGroupData::find()->where(['group' => trim(ucwords($model->group))])->one();
                $cek_reason = EskGroupReasonData::find()->where(['reason' => trim(ucwords($model->reason))])->one();

                if(empty($cek_group))
                {
                        if(empty($cek_reason)){
                            $sql = 'INSERT INTO esk_group_reason (id_group, reason, is_active) VALUE ('.$model2->id.',"'.trim(ucwords($model->reason)).'",'.$model->is_active.')';
    
                            Yii::$app->db->createCommand($sql)->execute();
        
                            Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                            return $this->redirect(['index']);
                        }else{
                            $cek_reason1 = EskGroupReasonData::find()->where(['reason' => trim(ucwords($model->reason)), 'id_group' => Null])->one();
                            if(!empty($cek_reason1)){

                                Yii::$app->db->createCommand()
                                ->update('esk_group_reason', ['id_group' => $model2->id, 'is_active' => $model->is_active], 'id = '.$cek_reason1->id.'')
                                ->execute();
            
                                Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                                return $this->redirect(['index']);
                            }else{
                                Yii::$app->session->setFlash('info', "Group data created successfully ! But");
                                Yii::$app->session->setFlash('warning', "This reason data already has a group !");
                            }
                        }
                        
                }else{
                    $cek_reason2 = EskGroupReasonData::find()->where(['reason' => trim(ucwords($model->reason)), 'id_group' => Null])->one();
                    if(!empty($cek_reason2)){

                        Yii::$app->db->createCommand()
                        ->update('esk_group_reason', ['id_group' => $cek_group->id, 'is_active' => $model->is_active], 'id = '.$cek_reason2->id.'')
                        ->execute();
    
                        Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                        return $this->redirect(['index']);
                    }else{
                        Yii::$app->session->setFlash('warning', "This reason data already has a group !!");
                    }
                }
            }else{
                Yii::$app->session->setFlash('info', "Grouping reason data already exists");
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionSaveGroup($group_name){
        $model = new EskGroupData();
        $model->group = trim(ucwords($group_name));
        $model->created_at = date("Y-m-d H:i:s");

        $cek_group = EskGroupData::find()->where(['group' => trim(ucwords($model->group))])->one();

        if(empty($cek_group)){
            if ($model->save()) {
                $data = array(
                    "result" => 1,
                    "remark" => "Success",
                );
            }else{
                $error = implode(",",$model->getErrorSummary(true));
                $data = array(
                    "result" => 0,
                    "remark" => "Failed save data because ".$error."!",
                );
            }
        }else{
            $data = array(
                "result" => 0,
                "remark" => "Failed save data because group name already exists",
            );
        }

        
        return json_encode($data);
    }


    /**
     * Updates an existing EvaluationData model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $nik
     * @param string $start_date_assigment
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        
        $model = EskGroupReasonData::find()->where(['id' => $id])->one();
        
        if ($model->load(Yii::$app->request->post())) { //if ($model->load(Yii::$app->request->post()) && $model->save())
            $model2 = new EskGroupData();
            $model2->group = trim(ucwords($model->group));
            $model2->updated_at = date("Y-m-d H:i:s");

                $cek_group = EskGroupData::find()->where(['group' => trim(ucwords($model->group))])->one();
                
                if(empty($cek_group))
                {
                        Yii::$app->db->createCommand()
                        ->update('esk_group_reason', ['id_group' => $model2->id, 'is_active' => $model->is_active], 'id = '.$id.'')
                        ->execute();
    
                        Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                        return $this->redirect(['index']);
                }else{
                        Yii::$app->db->createCommand()
                        ->update('esk_group_reason', ['id_group' => $cek_group->id, 'is_active' => $model->is_active], 'id = '.$id.'')
                        ->execute();
    
                        Yii::$app->session->setFlash('success', $model->group." successfully create grouping reason");
                        return $this->redirect(['index']);
                }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

	public function actionPopulateDataApi()
	{
		set_time_limit(0);
		ini_set('memory_limit', '5048M');
		
		$evaluation  = new EskGroupReasonData;
		$evaluation->populateDataFromApi;
		
		// echo "Success Sych evaluation data to ESK Application";
		// exit;
		Yii::$app->session->setFlash('success', 'Success Sych evaluation data to ESK Application');
		return $this->redirect(['esk-group-reason/index']);
	}
}
