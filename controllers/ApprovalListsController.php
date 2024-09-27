<?php

namespace esk\controllers;

use esk\components\Helper;
use Yii;
use esk\models\EskLists;
use esk\models\EskListsSearch;
use esk\models\EskApprovalLists;
use esk\models\EskAcknowledgeLists;
use esk\models\EskWorkflowLists;
use esk\models\EskFlagData;
use esk\models\Model;
use esk\models\Employee;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
use yii\db\Expression;

class ApprovalListsController extends Controller
{   
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['login', 'error','logout'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['admin'],
                        'allow' => true,
                        'roles' => ['sysadmin'],
                    ],
                    [
                        'actions' => ['acknowledge','acknowledgeall','acknowledge-lists','approval','approved','approvedall',
                        'approvedlists','index','dismiss','esk-lists','modalacknowledge','modalapproved','modalpublish',
                        'modalrejected','publish','published','publishedall','read-esk','rejected','rejectedall','update','view'],
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            //validation menu approved lists
                            $countApproved = Model::countApproved(); 
                            if($countApproved <= 0){
                                $visibleMenuApproval = false;
                            }else{
                                $visibleMenuApproval = true;
                            }

                            if($visibleMenuApproval){
                                return $visibleMenuApproval;
                            }

                            $countReview = Model::countApproval() + Model::countDelivered();
                            if($countReview <= 0){
                                $visibleMenu = false;
                            }else{
                                $visibleMenu = true;
                            }

                            if($visibleMenu){
                                return $visibleMenu;
                            }

                            //check if role sysadmin
                            if(Yii::$app->user->can('sysadmin')){
                               return true; 
                            }else{
                                return false;
                            }
                        }
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
        $flag_data = EskFlagData::find()->one()->flag_ack;
        $dataProvider = new ActiveDataProvider([
            'query' => EskApprovalLists::find()
                ->select(['esk_approval_lists.id','esk_approval_lists.id_esk','esk_approval_lists.approval_name','esk_approval_lists.status'])
                ->leftJoin('esk_lists','esk_lists.id = esk_approval_lists.id_esk')
                ->where(['esk_approval_lists.approval_nik' => Yii::$app->user->identity->nik])
                ->andWhere(['esk_approval_lists.status' => 'pending'])
                ->andWhere(['esk_lists.status' => 'processed'])
                ->andWhere('esk_lists.flag_approval_seq = esk_approval_lists.sequence'),
            'pagination' => [
				'pageSize' => 100,
			], 
        ]);
        
        if($flag_data == 1){
            $dataProvider2 = new ActiveDataProvider([
                'query' => EskLists::find()
                    ->select(['esk_lists.*'])
                    ->leftJoin('esk_acknowledge_lists','esk_lists.id = esk_acknowledge_lists.id_esk')
                    ->where(['esk_acknowledge_lists.ack_nik' => Yii::$app->user->identity->nik])
                    ->andWhere(['esk_acknowledge_lists.status' => 'pending'])
                    ->andWhere(['esk_lists.status' => 'delivered'])
                    ->andWhere('esk_lists.flag_ack_seq = esk_acknowledge_lists.sequence'),
                'pagination' => [
					'pageSize' => 100,
				],    
            ]);

            $data_esk = EskLists::find()
            ->select(['esk_lists.*'])
            ->leftJoin('esk_acknowledge_lists','esk_lists.id = esk_acknowledge_lists.id_esk')
            ->where(['esk_acknowledge_lists.ack_nik' => Yii::$app->user->identity->nik])
            ->andWhere(['esk_acknowledge_lists.status' => 'pending'])
            ->andWhere(['esk_lists.status' => 'delivered'])
            ->andWhere('esk_lists.flag_ack_seq = esk_acknowledge_lists.sequence')->one();
            if(!empty($data_esk)){
                $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one()->sequence;
                $max_sequence = EskAcknowledgeLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $data_esk->id])->one()->sequence;
            }else{
                $data_ack = 'n/a';
                $max_sequence = 0;
            }
        }else{
            $dataProvider2 = new ActiveDataProvider([
                'query' => EskLists::find()->where(['vp_nik' => Yii::$app->user->identity->nik])->andWhere(['status' => 'delivered']),
                'pagination' => [
					'pageSize' => 100,
				], 
            ]);
            
            $data_ack = 'n/a';
            $max_sequence = 0;

        }
        

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'dataProvider2' => $dataProvider2,
            'ack_seq' => $data_ack,
            'max_seq' => $max_sequence,
        ]);
    }

    public function actionAdmin()
    {   
        $flag_data = EskFlagData::find()->one()->flag_ack;
        //tampilkan semuanya
        $dataProvider = new ActiveDataProvider([
            'query' => EskApprovalLists::find()
                ->select(['esk_approval_lists.id','esk_approval_lists.id_esk','esk_approval_lists.approval_name','esk_approval_lists.status'])
                ->leftJoin('esk_lists','esk_lists.id = esk_approval_lists.id_esk')
                ->where('esk_lists.flag_approval_seq = esk_approval_lists.sequence')
                ->andWhere(['esk_approval_lists.status' => 'pending'])
                ->andWhere(['esk_lists.status' => 'processed']),
            'pagination' => false,    
        ]);

        if($flag_data == 1){
            $dataProvider2 = new ActiveDataProvider([
                'query' => EskLists::find()
                    ->select(['esk_lists.*'])
                    ->leftJoin('esk_acknowledge_lists','esk_lists.id = esk_acknowledge_lists.id_esk')
                    ->andWhere(['esk_acknowledge_lists.status' => 'pending'])
                    ->andWhere(['esk_lists.status' => 'delivered'])
                    ->andWhere('esk_lists.flag_ack_seq = esk_acknowledge_lists.sequence'),
                'pagination' => false,    
            ]);
        }else{
            $dataProvider2 = new ActiveDataProvider([
                'query' => EskLists::find()->where(['status' => 'delivered']),
                'pagination' => false,
            ]);
        }

        $data_ack = 'n/a';
        $max_sequence = 0;
        

        return $this->render('index_admin', [
            'dataProvider' => $dataProvider,
            'dataProvider2' => $dataProvider2,
            'ack_seq' => $data_ack,
            'max_seq' => $max_sequence,
        ]);
    }

    public function actionApprovedlists()
    {   
		set_time_limit(0);
        ini_set('memory_limit', '9000M');
		$tanggal =  date("Y") . '-01-01';
        
		if(Yii::$app->user->can('sysadmin')){
            //tampilkan semuanya
            $dataProvider = new ActiveDataProvider([
                'query' => EskLists::find()
                    ->select(['esk_lists.*'])
                    ->leftJoin('esk_approval_lists','esk_lists.id = esk_approval_lists.id_esk')
                    ->where(['esk_approval_lists.status' => 'approved']),     
            ]);
        }else{
            $dataProvider = new ActiveDataProvider([
                'query' => EskLists::find()
                    ->select(['esk_lists.*'])
                    ->leftJoin('esk_approval_lists','esk_lists.id = esk_approval_lists.id_esk')
                    ->where(['esk_approval_lists.approval_nik' => Yii::$app->user->identity->nik])
                    ->andWhere(['esk_approval_lists.status' => 'approved'])
					->andWhere(['>=', 'esk_lists.effective_esk_date',$tanggal]),   
            ]);
        }

        return $this->render('approved', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionApproval($id_approval,$id_esk){
        //get workflow data 
        $dataWorkFlow = EskWorkflowLists::find()->where(['id_esk' => $id_esk])->orderBy('created_at ASC')->all();

        //get data esk contentnya
        $model = $this->findModel($id_esk);
        if($model->status == "published" || $model->status == "approved"){
            $file_name = $model->file_name;
        }else{
            $file_name = "";
        }
        $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"preview");

        //get data last approval
        $max_sequence = EskApprovalLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $id_esk])->one();
        $data_approval = EskApprovalLists::find()->where(['id' => $id_approval])->one();

        return $this->render('approval', [
            'id_approval' => $id_approval,
            'max_sequence' => $max_sequence, 
            'data_approval' => $data_approval,
            'model' => $model,
            'content' => $all_content,
            "workflow" => $dataWorkFlow
        ]);
    }

    public function actionRejected(){
        $id_app = yii::$app->request->get('id_app_rejected');
        $id_esk = yii::$app->request->get('id_esk');
        $remark = yii::$app->request->get('remark');

        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("rejected", $id_app, $id_esk);
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $data_esk->flag_approval_seq = $data_update['flag_approval_seq'];
        if($data_esk->save()){
            //update data approval statusnya
            $data_app = EskApprovalLists::findOne($id_app);
            $data_app->status = "rejected";
            $data_app->rejected_at = date("Y-m-d H:i:s");
            $data_app->save();
            
            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $nik = Yii::$app->user->identity->nik;
            }else{
                 $nik = "";
            }

            if($data_app->approval_nik == $nik){
                $action = " Penolakan eSK oleh ".$data_app->approval_title;
            }else{
                $action = " Penolakan eSK oleh ".$data_app->approval_title." (action by HCBP Account/Area)";
            }
            Model::setWorkFlow($data_esk->id,$action,$remark);

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Rejected eSK data with ID ".$data_esk->id." by ".$data_app->approval_title);

            //options send mail 
            $subject = "[eSK] Rejected of eSK Number ".$data_esk->number_esk."";
            if($data_esk->authority == "HEAD OFFICE"){
                $to = Model::getHCOA($data_esk->authority, $data_esk->old_directorate, $data_esk->tipe);//getHC();
            }else{
                $to = Model::getHCBP('"'.$data_esk->authority.'"');
            }
            $content = $this->renderPartial('../../mail/mail-rejected',['esk' => $data_esk, 'remark' => $remark],true);
            Model::sendMailMultiple($to,$subject,$content);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully rejected!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed rejected eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed rejected eSK, because ".$error);
        }

        //cek ada lagi data approval atau tidak
        if(Model::countApproval() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionRejectedall(){
        $id_app_data = yii::$app->request->get('id_app_rejected');
		$id_app = explode(",",$id_app_data);
        $remark = yii::$app->request->get('remark');

        //inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();

		foreach($id_app as $id_app){
            //get data esk
            $app = EskApprovalLists::find()->where(['id' => $id_app])->one();

            //kirim datanya ke fungsi workflow status
			$data_update = Model::WorkFlowStatus("rejected", $id_app, $app->id_esk);
			$data_esk = $this->findModel($app->id_esk);
			$data_esk->status = $data_update['status'];
			$data_esk->tracking = $data_update['tracking'];
			$data_esk->flag_approval_seq = $data_update['flag_approval_seq'];
			if($data_esk->save()){
				//update data approval statusnya
				$data_app = EskApprovalLists::findOne($id_app);
				$data_app->status = "rejected";
				$data_app->rejected_at = date("Y-m-d H:i:s");
				$data_app->save();
				
				//save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
				if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
					$nik = Yii::$app->user->identity->nik;
				}else{
					 $nik = "";
				}

				if($data_app->approval_nik == $nik){
					$action = " Penolakan eSK oleh ".$data_app->approval_title;
				}else{
					$action = " Penolakan eSK oleh ".$data_app->approval_title." (action by HCBP Account/Area)";
				}
				Model::setWorkFlow($data_esk->id,$action,$remark);

				//logging data
				Model::saveLog(Yii::$app->user->identity->username, "Rejected eSK data with ID ".$data_esk->id." by ".$data_app->approval_title);

				//options send mail 
                $subject = "[eSK] Rejected of eSK Number ".$data_esk->number_esk."";
                $toMailArray = array();
                array_push($toMailArray, Model::getMailHeadCreated($data_esk->head_created));
                array_push($toMailArray, Model::getMailCreated($data_esk->created_by));
                $to = $toMailArray;
				$content = $this->renderPartial('../../mail/mail-rejected',['esk' => $data_esk, 'remark' => $remark],true);
				Model::sendMailMultiple($to,$subject,$content);

                //set success count
                $countSuccess = $countSuccess + 1;

			}else{
                //set failed count
                $countFailed = $countFailed + 1;

				//logging data
                $error = implode(",",$data_esk->getErrorSummary(true));
                array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." rejected eSK because ".$error);
				Model::saveLog(Yii::$app->user->identity->username, "Failed rejected eSK data for ID ".$data_esk->id." because ".$error);
            }
            
            //count iteration
            $countAll = $countAll + 1;
		}
        
        //check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
        }else{
            $failed_data = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully rejected ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 

        //cek ada lagi data approval atau tidak
        if(Model::countApproval() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionModalrejected(){
        $id = yii::$app->request->get('id');
        return $this->renderAjax('rejected',[
            "id" => $id 
        ]);
    }

    public function actionApproved(){
        $id_app = yii::$app->request->get('id_approval');
        $id_esk = yii::$app->request->get('id_esk');
        $effective_date = yii::$app->request->get('effective_date');

        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("approved", $id_app, $id_esk);
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $data_esk->flag_approval_seq = $data_update['flag_approval_seq'];
        if(!empty($data_update['approved_esk_date'])){ 
            $data_esk->approved_esk_date = $data_update['approved_esk_date'];
        }
        if(!empty($effective_date)){
            $data_esk->effective_esk_date = date("Y-m-d",strtotime($effective_date));
            $data_esk->content_esk = Model::regenerateEsk($id_esk);
        }
        if($data_esk->save()){
            //update data approval statusnya
            $data_app = EskApprovalLists::findOne($id_app);
            $data_app->status = "approved";
            $data_app->approved_at = date("Y-m-d H:i:s");
            $data_app->save();

            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $nik = Yii::$app->user->identity->nik;
            }else{
                 $nik = "";
            }

            if($data_app->approval_nik == $nik){
                $action = $data_app->approval_title." menyetujui draft pembuatan eSK.";
            }else{
                $action = $data_app->approval_title." menyetujui draft pembuatan eSK. (action by HCBP Account/Area)";
            }
            Model::setWorkFlow($data_esk->id,$action,"-");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Approved eSK data with ID ".$data_esk->id." by ".$data_app->approval_title);

            if(!empty($data_esk->approved_esk_date)){ 
                $subject = "[eSK] Request of Delivered eSK Number ".$data_esk->number_esk."";
                if($data_esk->authority == "HEAD OFFICE"){
                    $to = Model::getHCOA($data_esk->authority, $data_esk->old_directorate,$data_esk->tipe);//getHC();
                }else{
                    $to = Model::getHCBP('"'.$data_esk->authority.'"');
                }
                $content = $this->renderPartial('../../mail/mail-all-approved',['esk' => $data_esk],true);
                Model::sendMailMultiple($to,$subject,$content);
            }else{
                $subject = "[eSK] Request of Approval eSK Number ".$data_esk->number_esk."";
                $to = $data_app->next->approval_mail;
                $content = $this->renderPartial('../../mail/mail-approval',['esk' => $data_esk, 'approval' => $data_app->next->approval_name, 'remark' => ''],true);
                Model::sendMailOne($to,$subject,$content);
            }

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully approved!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed approved eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed approved eSK, because ".$error);
        }

        //cek ada lagi data approval atau tidak
        if(Model::countApproval() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionApprovedall(){
		set_time_limit(0);
		ini_set('memory_limit', '2048M');
		
        $id_app_data = yii::$app->request->get('id_approval');
        $id_app = explode(",",$id_app_data);
        
        //inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();
        $id_app_first_seq = array(); 

        foreach($id_app as $id_app){
            //get data esk
            $app = EskApprovalLists::find()->where(['id' => $id_app])->one();
            if($app->sequence == 1){
                array_push($id_app_first_seq,$app->id_esk);
            }

            //kirim datanya ke fungsi workflow status
            $data_update = Model::WorkFlowStatus("approved", $id_app, $app->id_esk);
            $data_esk = $this->findModel($app->id_esk);
            $data_esk->status = $data_update['status'];
            $data_esk->tracking = $data_update['tracking'];
            $data_esk->flag_approval_seq = $data_update['flag_approval_seq'];
			$data_esk->number_esk = $data_esk->number_esk; //$data_esk->sequence.$data_esk->no_esk;
            if(!empty($data_update['approved_esk_date'])){ 
                $data_esk->approved_esk_date = $data_update['approved_esk_date'];
            }

            if($data_esk->save()){
                //update data approval statusnya
                $data_app = EskApprovalLists::findOne($id_app);
                $data_app->status = "approved";
                $data_app->approved_at = date("Y-m-d H:i:s");
                $data_app->save();

                //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $nik = Yii::$app->user->identity->nik;
                }else{
                        $nik = "";
                }

                if($data_app->approval_nik == $nik){
                    $action = $data_app->approval_title." menyetujui draft pembuatan eSK.";
                }else{
                    $action = $data_app->approval_title." menyetujui draft pembuatan eSK. (action by HCBP Account/Area)";
                }
                Model::setWorkFlow($data_esk->id,$action,"-");

                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Approved eSK data with ID ".$data_esk->id." by ".$data_app->approval_title);

                if(!empty($data_esk->approved_esk_date)){ 
                    $subject = "[eSK] Request of Delivered eSK Number ".$data_esk->number_esk."";
                    if(!empty($data_esk->atasan_created)){
                        $to = Model::getManagerDeliver($data_esk->atasan_created);            
                    }else{
                        $to = Model::getAckManager($data_esk->authority,$data_esk->new_directorate,$data_esk->tipe);            
                    }
                    $content = $this->renderPartial('../../mail/mail-all-approved',['esk' => $data_esk],true);
                    if(!empty($to)){ Model::sendMailMultiple($to,$subject,$content); }
                }else{
                    $subject = "[eSK] Request of Approval eSK Number ".$data_esk->number_esk."";
                    $to = $data_app->next->approval_mail;
                    $content = $this->renderPartial('../../mail/mail-approval',['esk' => $data_esk, 'approval' => $data_app->next->approval_name, 'remark' => ''],true);
                    Model::sendMailOne($to,$subject,$content);
                }

                //set success count
                $countSuccess = $countSuccess + 1;
            }else{
                //set failed count
                $countFailed = $countFailed + 1;
                
                //logging data
                $error = implode(",",$data_esk->getErrorSummary(true));
                array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." approved eSK because ".$error);
                Model::saveLog(Yii::$app->user->identity->username, "Failed approved eSK data for ID ".$data_esk->id." because ".$error);
            }
            
            //count iteration
            $countAll = $countAll + 1;
        }
            
        //check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
        }else{
            $failed_data = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully approved ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 

        //validasi jika sequence approval pertama
        if(!empty($id_app_first_seq)){
            $emailArray = array();
            foreach($id_app_first_seq as $id_app){
                $esk = EskLists::findOne($id_app);
                array_push($emailArray, 
                    "
                    <tr>
                        <td width='20%'>" . $esk->number_esk . "</td>
                        <td width='15%'>" . $esk->nik . "</td>
                        <td width='25%'>" . $esk->nama . "</td>
                        <td width='20%'>" . ucwords($esk->about_esk) . "</td>
                        <td width='20%'>" . Model::TanggalIndo($esk->effective_esk_date) . "</td>
                    </tr>
                    "
                );
            }

            //get GM HCOA
            $gmhcoa = Employee::find()->where(['position_id' => '110984'])->one();
            if(!empty($gmhcoa)){
                //send mail to GM head created
                $subject = "[eSK] Information of Processing eSK";
                $to = $gmhcoa->email;
                $content = $this->renderPartial('../../mail/mail-information',['head' => $gmhcoa->nama, 'esk' => $emailArray],true);
                Model::sendMailOne($to,$subject,$content);
            }
        }

        //cek ada lagi data approval atau tidak
        if(Model::countApproval() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionModalapproved(){
        $id = yii::$app->request->get('id');
        $id_array = explode(",",$id);
        $data_backdate = array();
        $count_flag = 0;

        foreach($id_array as $id_app){
            //get id esk 
            $data_app = EskApprovalLists::find()->where(['id' => $id_app])->one();
            
            //get data esk
            $data_esk = EskLists::find()->where(['id' => $data_app->id_esk])->one();
            $max_sequence = EskApprovalLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $data_esk->id])->one();
            
            //count dari banyak data apakah ada yg merupakan last approval
            if($data_app->sequence == $max_sequence->sequence  && $data_esk->flag_backdate == '1'){
                array_push($data_backdate,$data_esk->nik."/".$data_esk->nama);
                $count_flag++;
            }
        }
       
        if($count_flag > 0){
            $data_backdate = implode(", ",$data_backdate);
        }else{
            $data_backdate = "";
        }

        return $this->renderAjax('appdialog',[
            "id" => $id,
            "flag_backdate" => $count_flag, 
            "data_backdate" => $data_backdate,
        ]);
    }

    public function actionAcknowledge($id,$id_ack){
        //get data acknowledge 
        //$ack = EskAcknowledgeLists::find()->where(['id_esk' => $id])->one();
        //$countPending = EskAcknowledgeLists::find()->where(['id_esk' => $id])->andWhere(['status' => 'pending'])->count();

        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("delivered", $id_ack, $id);
        $data_esk = $this->findModel($id);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $data_esk->flag_ack_seq = $data_update['flag_approval_seq'];
        
        if($data_esk->save()){
            //update data approval statusnya
            $data_ack = EskAcknowledgeLists::findOne($id_ack);
            $data_ack->status = "acknowledge";
            $data_ack->ack_at = date("Y-m-d H:i:s");
            $data_ack->save();

            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $nik = Yii::$app->user->identity->nik;
            }else{
                 $nik = "";
            }

            if($data_ack->ack_nik == $nik){
                $action = $data_ack->ack_title." mengakui eSK Karyawan.";
            }else{
                $action = $data_ack->ack_title." mengakui eSK Karyawan. (action by HCBP Account/Area)";
            }
            Model::setWorkFlow($data_esk->id,$action,"-");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Acknowledge eSK data with ID ".$data_esk->id." by ".$data_ack->ack_title);

            //send mail
            $subject = "[eSK] Delivered of eSK Number ".$data_esk->number_esk."";
            $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
            $content = $this->renderPartial('../../mail/mail-delivered',['esk' => $data_esk, 'head' => $data_ack->ack_name],true);        
            Model::sendMailOne($data_ack->ack_mail,$subject,$content);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully acknowledge!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed acknowledge eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed acknowledge eSK, because ".$error);
        }

        //cek ada lagi data approval atau tidak
        if(Model::countDelivered() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionAcknowledgeall(){
        $id_esk_data = yii::$app->request->get('id_esk');
		$id_esk = explode(",",$id_esk_data);
				
		//inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();
        
        foreach($id_esk as $id_esk){
			//get data esk
			$data_esk = $this->findModel($id_esk);

			//get data ack 
			$ack = EskAcknowledgeLists::find()->where(['id_esk' => $id_esk, 'sequence' => $data_esk->flag_ack_seq])->one();
			
			//get data acknowledge 
			//$ack = EskAcknowledgeLists::find()->where(['id_esk' => $id_esk])->one();
			$countPending = EskAcknowledgeLists::find()->where(['id_esk' => $id_esk,'status' => 'pending'])->count();

            //kirim datanya ke fungsi workflow status
            if($countPending <= 0){
                $data_update = Model::WorkFlowStatus("published", '', $id_esk);
                $flag_publish = true;
            }else{
                $data_update = Model::WorkFlowStatus("delivered", $ack->id, $id_esk);
                $data_esk->flag_ack_seq = $data_update['flag_approval_seq'];	
                $flag_publish = false;
            }
			$data_esk->status = $data_update['status'];
			$data_esk->tracking = $data_update['tracking'];
			
			if($data_esk->save()){
				//update data approval statusnya
				$data_ack = EskAcknowledgeLists::findOne($ack->id);
				$data_ack->status = "acknowledge";
				$data_ack->ack_at = date("Y-m-d H:i:s");
				$data_ack->save();

				//save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
				if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
					$nik = Yii::$app->user->identity->nik;
				}else{
					 $nik = "";
				}

                if($flag_publish){
                    if($data_ack->ack_nik == $nik){
                        $action = $data_ack->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                    }else{
                        $action = $data_ack->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.". (action by HCBP Account/Area)";
                    }
                    $published_by = $data_ack->ack_title;

                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$published_by);
                    
                    //submit posting career 
                    Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

                    //send mail to other ack
                    $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
                    $to = Model::getOtherAck($data_esk->id, $ack->id);
                    $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk],true);
                    Model::sendMailMultiple($to,$subject,$content);

                    $to = $data_esk->employee->email;
                    $content = $this->renderPartial('../../mail/mail-published',['data_esk' => $data_esk],true);
                    Model::sendNotifMoana($to,'My Assignment • New Update',ucwords(strtolower($data_esk->about_esk)));
                    Model::sendMailOne($to,$subject,$content);

                    //cek band
                    $databp = explode(".",$data_esk->new_bp);                
                    if($databp[0] == 5 || $databp[0] == 6){
                        $to = Model::getDirectionMail($data_esk->new_directorate);
                    }else{
                        $to = Model::getHCBPOfficers($data_esk->authority,$data_esk->new_directorate);
                    }
                    
                    //send mail to hcbp area
                    $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk],true);
                    Model::sendMailMultiple($to,$subject,$content);
                }else{
                    if($data_ack->ack_nik == $nik){
                        $action = $data_ack->ack_title." mengakui eSK Karyawan.";
                    }else{
                        $action = $data_ack->ack_title." mengakui eSK Karyawan. (action by HCBP Account/Area)";
                    }
    
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Acknowledge eSK data with ID ".$data_esk->id." by ".$data_ack->ack_title);

                    //send mail
                    $subject = "[eSK] Delivered of eSK Number ".$data_esk->number_esk."";
                    $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
                    $content = $this->renderPartial('../../mail/mail-delivered',['esk' => $data_esk, 'head' => $data_ack->ack_name],true);        
                    Model::sendMailOne($data_ack->ack_mail,$subject,$content);
                }
				Model::setWorkFlow($data_esk->id,$action,"-");

				//set success count
                $countSuccess = $countSuccess + 1;
			}else{
				//set failed count
                $countFailed = $countFailed + 1;
                $error = implode(",",$data_esk->getErrorSummary(true));
				if($flag_publish){
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Failed published eSK data for ID ".$data_esk->id." because ".$error);
                    array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed published eSK because ".$error);
                }else{
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Failed acknowledge eSK data for ID ".$data_esk->id." because ".$error);
                    array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed acknowledge eSK because ".$error);
                }    
            }
			
			//count iteration
            $countAll = $countAll + 1;
		}
		
		 //check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
        }else{
            $failed_data = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully acknowledge ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 

		
        //cek ada lagi data approval atau tidak
        if(Model::countDelivered() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionAcknowledgeLists()
    {   
        if(Yii::$app->user->can('sysadmin')){
            //tampilkan semuanya
			$data_provider_ack_lists_read = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                    'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                    'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_acknowledge_lists.ack_nik as nik_ack', 'esk_acknowledge_lists.id as id_ack'])
            ->leftJoin('esk_acknowledge_lists','esk_lists.id = esk_acknowledge_lists.id_esk')
            ->where(['esk_acknowledge_lists.status' => 'acknowledge','esk_acknowledge_lists.flag_dismiss' => 0])->all();
			
            $data_provider_ack_read = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_acknowledge_settings.nik as nik_ack',
                'id_ack' => new Expression('0')])    
            ->join('JOIN','esk_acknowledge_settings','esk_lists.tipe = esk_acknowledge_settings.tipe AND esk_lists.authority = esk_acknowledge_settings.authority_area')
            ->where(['esk_lists.status' => 'published'])
            ->andWhere('(
                (esk_acknowledge_settings.authority_area LIKE "%AREA%") ||
                (esk_lists.new_directorate = esk_acknowledge_settings.directorate AND esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category = 1 || 
                esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category != 1)
                )')
            ->all();

            $data_provider_template_read = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_lists.vp_nik as nik_ack',
                'id_ack' => new Expression('0')])
            ->join('JOIN','esk_template_master','esk_lists.code_template = esk_template_master.code_template')
            ->where(['esk_lists.status' => 'published','esk_template_master.flag_deliver_to' => '2'])
            ->all();

            $data_read = ArrayHelper::merge($data_provider_ack_read,$data_provider_ack_read, $data_provider_template_read);

            $dataProvider_read = new ArrayDataProvider([
                'allModels' => $data_read,
                'pagination' => false,    
            ]);

            $dataProvider_unread = "";
        }else{
            //tampilkan unread semuanya
			$data_provider_ack_lists_unread = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                    'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                    'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_acknowledge_lists.ack_nik as nik_ack', 'esk_acknowledge_lists.id as id_ack'])
            ->leftJoin('esk_acknowledge_lists','esk_lists.id = esk_acknowledge_lists.id_esk')
            ->where(['esk_acknowledge_lists.ack_nik' => Yii::$app->user->identity->nik])
            ->andWhere('(esk_lists.read_ack LIKE "%'.Yii::$app->user->identity->employee->nik.'%") IS NOT TRUE')   
            ->andWhere(['esk_acknowledge_lists.status' => 'acknowledge','esk_acknowledge_lists.flag_dismiss' => 0])->all();
					
            $data_provider_ack_unread = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_acknowledge_settings.nik as nik_ack',
                'id_ack' => new Expression('0')]) 
            ->join('JOIN','esk_acknowledge_settings','esk_lists.tipe = esk_acknowledge_settings.tipe AND esk_lists.authority = esk_acknowledge_settings.authority_area')
            ->where(['esk_lists.status' => 'published','esk_acknowledge_settings.nik' => Yii::$app->user->identity->employee->nik])
            ->andWhere('(
                (esk_acknowledge_settings.authority_area LIKE "%AREA%") ||
                (esk_lists.new_directorate = esk_acknowledge_settings.directorate AND esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category = 1 || 
                esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category != 1)
                )')
            ->andWhere('(esk_lists.read_ack LIKE "%'.Yii::$app->user->identity->employee->nik.'%") IS NOT TRUE')   
            ->all();

            $data_provider_template_unread = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_lists.vp_nik as nik_ack',
                'id_ack' => new Expression('0')])
            ->join('JOIN','esk_template_master','esk_lists.code_template = esk_template_master.code_template')
            ->where(['esk_lists.status' => 'published','esk_lists.vp_nik' => Yii::$app->user->identity->employee->nik,'esk_template_master.flag_deliver_to' => '2'])
            ->andWhere('(esk_lists.read_ack LIKE "%'.Yii::$app->user->identity->employee->nik.'%") IS NOT TRUE')   
            ->all();

            $data_unread = ArrayHelper::merge($data_provider_ack_lists_unread,$data_provider_ack_unread, $data_provider_template_unread);

            $dataProvider_unread = new ArrayDataProvider([
                'allModels' => $data_unread,
                'pagination' => false,    
            ]);

            //=== read start ===//
            $data_provider_ack_lists_read = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                    'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                    'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_acknowledge_lists.ack_nik as nik_ack', 'esk_acknowledge_lists.id as id_ack'])
            ->leftJoin('esk_acknowledge_lists','esk_lists.id = esk_acknowledge_lists.id_esk')
            ->where(['esk_acknowledge_lists.ack_nik' => Yii::$app->user->identity->nik])
            ->andWhere('(esk_lists.read_ack LIKE "%'.Yii::$app->user->identity->employee->nik.'%") IS TRUE')   
            ->andWhere(['esk_acknowledge_lists.status' => 'acknowledge','esk_acknowledge_lists.flag_dismiss' => 0])->all();
					
            $data_provider_ack_read = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_acknowledge_settings.nik as nik_ack',
                'id_ack' => new Expression('0')]) 
            ->join('JOIN','esk_acknowledge_settings','esk_lists.tipe = esk_acknowledge_settings.tipe AND esk_lists.authority = esk_acknowledge_settings.authority_area')
            ->where(['esk_lists.status' => 'published','esk_acknowledge_settings.nik' => Yii::$app->user->identity->employee->nik])
            ->andWhere('(
                (esk_acknowledge_settings.authority_area LIKE "%AREA%") ||
                (esk_lists.new_directorate = esk_acknowledge_settings.directorate AND esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category = 1 || 
                esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category != 1)
                )')
            ->andWhere('(esk_lists.read_ack LIKE "%'.Yii::$app->user->identity->employee->nik.'%") IS TRUE')       
            ->all();

            $data_provider_template_read = EskLists::find()
            ->select(['esk_lists.nik', 'esk_lists.nama', 'esk_lists.old_position', 'esk_lists.new_position', 'esk_lists.old_bi', 
                'esk_lists.new_bi', 'esk_lists.old_bp', 'esk_lists.new_bp', 'esk_lists.old_kota', 'esk_lists.new_kota', 
                'esk_lists.about_esk', 'esk_lists.effective_esk_date', 'esk_lists.status', 'esk_lists.id as id_esk', 'esk_lists.vp_nik as nik_ack',
                'id_ack' => new Expression('0')])
            ->join('JOIN','esk_template_master','esk_lists.code_template = esk_template_master.code_template')
            ->where(['esk_lists.status' => 'published','esk_lists.vp_nik' => Yii::$app->user->identity->employee->nik,'esk_template_master.flag_deliver_to' => '2'])
            ->andWhere('(esk_lists.read_ack LIKE "%'.Yii::$app->user->identity->employee->nik.'%") IS TRUE')   
            ->all();

            $data_read = ArrayHelper::merge($data_provider_ack_lists_read,$data_provider_ack_read, $data_provider_template_read);

            $dataProvider_read = new ArrayDataProvider([
                'allModels' => $data_read,
                'pagination' => false,    
            ]);
        }
        
        return $this->render('acknowledge', [
            'dataProviderUnread' => $dataProvider_unread,
            'dataProviderRead' => $dataProvider_read,
        ]);
    }

    public function actionDismiss($id_ack){
        //get data acknowledge 
        $data_ack = EskAcknowledgeLists::findOne($id_ack);
        $data_ack->flag_dismiss = 1;
        $data_ack->save();

        if($data_ack->save()){
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "User hide Acknowledge eSK data with ID ".$data_ack->id." by ".$data_ack->ack_title);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK Acknowledge data successfully dismiss!");
        }else{
            //logging data
            $error = implode(",",$data_ack->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed dismiss acknowledge eSK data for ID ".$data_ack->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed dismiss acknowledge eSK, because ".$error);
        }

        return $this->redirect(['acknowledge-lists']);        
    }

    public function actionReadEsk()
    {   
        $id = yii::$app->request->get('id');
        $model = $this->findModel($id);

        //get nik user 
        if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
            $nik = Yii::$app->user->identity->nik;
        }else{
             $nik = "-";
        }

        if(!empty($model->read_ack)){
            $read_by = $model->read_ack.", ".$nik;
        }else{
            $read_by = $nik;
        }
        
        $model->read_ack = $read_by;

        if($model->save()){
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Read eSK with ID ".$model->id);

            Yii::$app->session->setFlash('success', "eSK data successfully read!");
        }else{
            //logging data
            $error = implode(",",$model->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed read eSK data for ID ".$model->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed read eSK, because ".$error);
        }

        return $this->redirect(['approval-lists/acknowledge-lists']);
    }

    public function actionModalacknowledge(){
        $id = yii::$app->request->get('id');
        return $this->renderAjax('ackdialog',[
            "id" => $id 
        ]);
    }

    public function actionPublish($id_esk){
        //get workflow data 
        $dataWorkFlow = EskWorkflowLists::find()->where(['id_esk' => $id_esk])->orderBy('created_at ASC')->all();

        //get data esk contentnya
        $model = $this->findModel($id_esk);
        $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $model->id, 'sequence' => $model->flag_ack_seq])->one();
        $max_sequence = EskAcknowledgeLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $model->id])->one();
        if($model->status == "published" || $model->status == "approved"){
            $file_name = $model->file_name;
        }else{
            $file_name = "";
        }
        $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"preview");

        return $this->render('publish', [
            'model' => $model,
            'data_ack' => $data_ack,
            'max_seq' => $max_sequence->sequence,
            'content' => $all_content,
            "workflow" => $dataWorkFlow
        ]);
    }

    public function actionPublished(){
        $id_esk = yii::$app->request->get('id_esk');
        $id_ack = yii::$app->request->get('id_ack');

        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("published", '', $id_esk);
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        
        if($data_esk->save()){
            //get nik user 
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $nik = Yii::$app->user->identity->nik;
            }else{
                 $nik = "";
            }

            //update ack jika tidak kosong
            if(!empty($id_ack)){
                //update data approval statusnya
                $data_ack = EskAcknowledgeLists::findOne($id_ack);
                $data_ack->status = "acknowledge";
                $data_ack->ack_at = date("Y-m-d H:i:s");
                $data_ack->save();
                $max_sequence = EskAcknowledgeLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $id_esk])->one();

                //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
                if($data_ack->ack_nik == $nik){
                    $action = $data_ack->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                }else{
                    $action = $data_ack->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.". (action by HCBP Account/Area)";
                }
                $published_by = $data_ack->ack_title;

                //cek apakah last sequence
                if($data_ack->sequence != $max_sequence->sequence){
                    //update all status ack if not last last sequence
                    $update_all_ack = EskAcknowledgeLists::updateAll(['status' => 'Skipped for Acknowledge Action'], 'id != '.$data_ack->id.' and id_esk = '.$id_esk);

                    //send mail to other ack
                    $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
                    $to = Model::getOtherAck($id_esk, $id_ack);
                    $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk],true);
                    Model::sendMailMultiple($to,$subject,$content);
                }
            }else{
                if($data_esk->vp_nik == $nik){
                    $action = $data_esk->vP->title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                }else{
                    $action = $data_esk->vP->title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.". (action by HCBP Account/Area)";
                }
                $published_by = $data_esk->vP->title;
            }
            Model::setWorkFlow($data_esk->id,$action,"-");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$published_by);

            //submit posting career 
            Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

            $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
			$to = $data_esk->employee->email;
			$content = $this->renderPartial('../../mail/mail-published',['data_esk' => $data_esk],true);
            Model::sendNotifMoana($to,'My Assignment • New Update',ucwords(strtolower($data_esk->about_esk)));
			Model::sendMailOne($to,$subject,$content);

            //cek band
            $databp = explode(".",$data_esk->new_bp);                
            if($databp[0] == 5 || $databp[0] == 6){
                $to = Model::getDirectionMail($data_esk->new_directorate);
            }else{
                $to = Model::getHCBPOfficers($data_esk->authority,$data_esk->new_directorate);
            }
            
            //send mail to hcbp area
            $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
            $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk],true);
            Model::sendMailMultiple($to,$subject,$content);
            
            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully published!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed published eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed published eSK, because ".$error);
        }

        //cek ada lagi data approval atau tidak
        if(Model::countDelivered() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionPublishedall(){
        $id_esk_data = yii::$app->request->get('id_esk');
		$id_esk = explode(",",$id_esk_data);
				
		//inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();
        
        foreach($id_esk as $id_esk){
			//kirim datanya ke fungsi workflow status
			$data_update = Model::WorkFlowStatus("published", '', $id_esk);
			$data_esk = $this->findModel($id_esk);
			$data_esk->status = $data_update['status'];
			$data_esk->tracking = $data_update['tracking'];
			
			//data ack 
			$data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
            $max_sequence = EskAcknowledgeLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $data_esk->id])->one();

			//flag ack 
			$flag_ack = EskFlagData::find()->one()->flag_ack;

			if($data_esk->save()){
				//get nik user 
				if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
					$nik = Yii::$app->user->identity->nik;
				}else{
					 $nik = "";
				}

				//update ack jika tidak kosong
				if(!empty($data_ack) && $flag_ack == 1){
					//update data approval statusnya
					$data_ack = EskAcknowledgeLists::findOne($data_ack->id);
					$data_ack->status = "acknowledge";
					$data_ack->ack_at = date("Y-m-d H:i:s");
					$data_ack->save();

					//save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
					if($data_ack->ack_nik == $nik){
						$action = $data_ack->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
					}else{
						$action = $data_ack->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.". (action by HCBP Account/Area)";
					}
                    $published_by = $data_ack->ack_title;
                    
                    //cek apakah last sequence
                    if($data_ack->sequence != $max_sequence->sequence){
                        //update all status ack if not last last sequence
                        $update_all_ack = EskAcknowledgeLists::updateAll(['status' => 'Skipped for Acknowledge Action'], 'id != '.$data_ack->id.' and id_esk = '.$id_esk);

                        //send mail to other ack
                        $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
                        $to = Model::getOtherAck($data_esk->id, $data_ack->id);
                        $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk],true);
                        Model::sendMailMultiple($to,$subject,$content);
                    }
				}else{
					if($data_esk->vp_nik == $nik){
						$action = $data_esk->vP->title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
					}else{
						$action = $data_esk->vP->title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.". (action by HCBP Account/Area)";
					}
					$published_by = $data_esk->vP->title;
				}
				Model::setWorkFlow($data_esk->id,$action,"-");

				//logging data
				Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$published_by);

                //submit posting career 
                Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

                //send mail to employee
				$subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
				$to = $data_esk->employee->email;
				$content = $this->renderPartial('../../mail/mail-published',['data_esk' => $data_esk],true);
                Model::sendNotifMoana($to,'My Assignment • New Update',ucwords(strtolower($data_esk->about_esk)));
				Model::sendMailOne($to,$subject,$content);

                //cek band
                $databp = explode(".",$data_esk->new_bp);                
                if($databp[0] == 5 || $databp[0] == 6){
                    $to = Model::getDirectionMail($data_esk->new_directorate);
                }else{
                    $to = Model::getHCBPOfficers($data_esk->authority,$data_esk->new_directorate);
                }
 
                //send mail to hcbp area
                $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
                $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk],true);
                Model::sendMailMultiple($to,$subject,$content);

				//set success count
                $countSuccess = $countSuccess + 1;
			}else{
				//set failed count
                $countFailed = $countFailed + 1;
				
				//logging data
				$error = implode(",",$data_esk->getErrorSummary(true));
				Model::saveLog(Yii::$app->user->identity->username, "Failed published eSK data for ID ".$data_esk->id." because ".$error);
				array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed published eSK because ".$error);

			}
			
			//count iteration
            $countAll = $countAll + 1;
		}
		
		 //check failed
        if(!empty($failed_array)){
            $failed_data = "that is ".implode(", ",array_unique($failed_array));
        }else{
            $failed_data = "";
        }

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully publish ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 

        //cek ada lagi data approval atau tidak
        if(Model::countDelivered() <= 0){
            //redirect ke site index
            return $this->redirect(['site/index']);
        }else{
            return $this->redirect(['index']);
        }
    }

    public function actionModalpublish(){
        $id = yii::$app->request->get('id');
        return $this->renderAjax('publishdialog',[
            "id" => $id 
        ]);
    }

    public function actionUpdate(){
        //get data params
        $id_esk = yii::$app->request->get('id_esk');
        $effective_date = yii::$app->request->get('effective_date');

        //get data esk
        $data_esk = $this->findModel($id_esk);
        if(!empty($effective_date)){
            $data_esk->effective_esk_date = date("Y-m-d",strtotime($effective_date));
            $data_esk->content_esk = Model::regenerateEsk($id_esk);
        }

        if($data_esk->save()){
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Update eSK data with ID ".$data_esk->id);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully updated!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed update eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed updated eSK, because ".$error);
        }

        return $this->redirect(['index']);
    }

    public function actionView($id,$id_app)
    {   
        //get data last approval
        $max_sequence = EskApprovalLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $id])->one();
        $data_approval = EskApprovalLists::find()->where(['id' => $id_app])->one();

        $model = $this->findModel($id);
        $old_atasan = Employee::find()->where(['nik' => $model->nik])->one();
        $new_atasan = Employee::find()->where(['nik' => $model->nik_new_atasan])->one();

        return $this->renderAjax('view', [
            'model' => $model, //$this->findModel($id),
            'old_atasan' => $old_atasan->nama_atasan,
            'new_atasan' => $new_atasan->nama,
            'data_approval' => $data_approval,
            'max_sequence' => $max_sequence
        ]);
    }

    public function actionEskLists()
    {   
        if(Yii::$app->user->can('sysadmin')){
            //tampilkan semuanya
            $data_provider_ack = EskLists::find()
            ->select('esk_lists.nik, esk_lists.nama, esk_lists.old_position, esk_lists.new_position, esk_lists.old_bi, 
                esk_lists.new_bi, esk_lists.old_bp, esk_lists.new_bp, esk_lists.old_kota, esk_lists.new_kota, 
                esk_lists.about_esk, esk_lists.effective_esk_date, esk_lists.status, esk_lists.id as id_esk, esk_acknowledge_settings.nik as nik_ack')
            ->join('JOIN','esk_acknowledge_settings','esk_lists.tipe = esk_acknowledge_settings.tipe AND esk_lists.authority = esk_acknowledge_settings.authority_area')
            ->where(['esk_lists.status' => 'published'])
            ->andWhere('(
                (esk_acknowledge_settings.authority_area LIKE "%AREA%") ||
                (esk_lists.new_directorate = esk_acknowledge_settings.directorate AND esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category = 1 || 
                esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category != 1)
                )')
            ->all();

            $data_provider_template = EskLists::find()
            ->select('esk_lists.nik, esk_lists.nama, esk_lists.old_position, esk_lists.new_position, esk_lists.old_bi, 
                esk_lists.new_bi, esk_lists.old_bp, esk_lists.new_bp, esk_lists.old_kota, esk_lists.new_kota, 
                esk_lists.about_esk, esk_lists.effective_esk_date, esk_lists.status, esk_lists.id as id_esk, esk_lists.vp_nik as nik_ack')
            ->join('JOIN','esk_template_master','esk_lists.code_template = esk_template_master.code_template')
            ->where(['esk_lists.status' => 'published','esk_template_master.flag_deliver_to' => '2'])
            ->all();

            $data = ArrayHelper::merge($data_provider_ack, $data_provider_template);

            $dataProvider = new ArrayDataProvider([
                'allModels' => $data,
                'pagination' => false,    
            ]);
        }else{
            //tampilkan semuanya
            $data_provider_ack = EskLists::find()
            ->select('esk_lists.nik, esk_lists.nama, esk_lists.old_position, esk_lists.new_position, esk_lists.old_bi, 
                esk_lists.new_bi, esk_lists.old_bp, esk_lists.new_bp, esk_lists.old_kota, esk_lists.new_kota, 
                esk_lists.about_esk, esk_lists.effective_esk_date, esk_lists.status, esk_lists.id as id_esk, esk_acknowledge_settings.nik as nik_ack')
            ->join('JOIN','esk_acknowledge_settings','esk_lists.tipe = esk_acknowledge_settings.tipe AND esk_lists.authority = esk_acknowledge_settings.authority_area')
            ->where(['esk_lists.status' => 'published','esk_acknowledge_settings.nik' => Yii::$app->user->identity->employee->nik])
            ->andWhere('(
                (esk_acknowledge_settings.authority_area LIKE "%AREA%") ||
                (esk_lists.new_directorate = esk_acknowledge_settings.directorate AND esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category = 1 || 
                esk_acknowledge_settings.authority_area LIKE "%HEAD%" AND esk_acknowledge_settings.category != 1)
                )')
            ->all();

            $data_provider_template = EskLists::find()
            ->select('esk_lists.nik, esk_lists.nama, esk_lists.old_position, esk_lists.new_position, esk_lists.old_bi, 
                esk_lists.new_bi, esk_lists.old_bp, esk_lists.new_bp, esk_lists.old_kota, esk_lists.new_kota, 
                esk_lists.about_esk, esk_lists.effective_esk_date, esk_lists.status, esk_lists.id as id_esk, esk_lists.vp_nik as nik_ack')
            ->join('JOIN','esk_template_master','esk_lists.code_template = esk_template_master.code_template')
            ->where(['esk_lists.status' => 'published','esk_lists.vp_nik' => Yii::$app->user->identity->employee->nik,'esk_template_master.flag_deliver_to' => '2'])
            ->all();

            $data = ArrayHelper::merge($data_provider_ack, $data_provider_template);

            $dataProvider = new ArrayDataProvider([
                'allModels' => $data,
                'pagination' => false,    
            ]);
        }
        
        return $this->render('esklists', [
            'dataProvider' => $dataProvider,
        ]);
    }

    protected function findModel($id)
    {
        if (($model = EskLists::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
