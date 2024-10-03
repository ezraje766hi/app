<?php

namespace esk\controllers;

use esk\components\Helper;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use kartik\mpdf\Pdf;
use yii\data\ActiveDataProvider;
use moonland\phpexcel\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use yii\web\UploadedFile;
use yii\helpers\Html;
use esk\models\EskLists;
use esk\models\EskDecreeBy;
use esk\models\EskListsSearch;
use esk\models\EskApprovalLists;
use esk\models\EskAcknowledgeLists;
use esk\models\EskAcknowledgeSettings;
use esk\models\EskBeritaAcaraDetailOther;
use esk\models\EskWorkflowLists;
use esk\models\EskFlagData;
use esk\models\Model;
use esk\models\Employee;
use esk\models\EskTemplateMaster;
use esk\models\EskAuthHc;
use esk\models\Position;
use yii\db\mssql\PDO;
// add by faqih sprint 4
use esk\models\EskGroupReasonData;
// 

/**
 * EskListsController implements the CRUD actions for EskLists model.
 */
class EskListsController extends Controller
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
                        'actions' => ['login', 'error', 'qrcode', 'cronack','print', 'modal-publish', 'modal-synchronize', 'check-publish', 'published-all','index'],
                        'allow' => true,
                    ],
                    [
                        'allow' => true,
                        'roles' => ['sysadmin','hc_staffing','hcbp_account', 'hc_view', 'hcm_view'],
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
	
	public function actionDownloadAllDataCsv()
	{
		set_time_limit(0);
        ini_set('memory_limit', '8048M');
		$connection = Yii::$app->getDb();
		
		$command = $connection->createCommand("
			SELECT 
				id, 
				code_template AS 'code',
				tipe, 
				nik, 
				nama AS 'name', 
				about_esk AS 'about', 
				number_esk AS 'eSK Number', 
				old_position, 
				new_position, 
				old_organization, 
				new_organization, 
				old_bp, 
				new_bp, 
				old_bi, 
				new_bi, 
				old_area, 
				new_area, 
				old_kota, 
				new_kota, 
				effective_esk_date, 
				tracking AS 'status', 
				old_directorate,
				new_directorate, 
				functional, 
				structural, 
				kr_organisasi_bss, 
				level_gaji, 
				gaji_dasar_nss AS 'gaji_dasar_bss', 
				tbh_nss AS 'tbh_bss', 
				tunjab_nss AS 'tunjab_bss'
			FROM 
				esk_lists
			");
		
		$result = $command->queryAll();
		
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="all-data-esk.csv"');
		$user_CSV[0] = array('ID','CODE', 'TIPE', 'NIK', 'NAME', 'ABOUT' , 'NUMBER' , 'OLD_POSITION' , 'NEW_POSITION' ,'OLD_ORGANIZATION', 'NEW_ORGANIZATION', 'OLD_BP', 'NEW_BP','OLD_BI','NEW_BI','OLD_AREA','NEW_AREA','OLD_KOTA','NEW_KOTA','EFFECTIVE_ESK_DATE','STATUS','OLD_DIRECTORATE','NEW_DIRECTORATE','FUNCTIONAL','STRUCTURAL','KR_ORGANISASI BSS','LEVEL_GAJI','GAJI_DASAR_BSS','TBH_BSS','TUNJAB_BSS');
		
		$i = 0;
		foreach($result as $rows) {
			$i = $i + 1;
			$user_CSV[$i] = array($rows['id'],$rows['code'],$rows['tipe'],$rows['nik'],$rows['name'],$rows['about'],$rows['eSK Number'],$rows['old_position'],$rows['new_position'],$rows['old_organization'],$rows['new_organization'],$rows['old_bp'],$rows['new_bp'],$rows['old_bi'],$rows['new_bi'],$rows['old_area'],$rows['new_area'],$rows['old_kota'],$rows['new_kota'],$rows['effective_esk_date'],$rows['status'],$rows['old_directorate'],$rows['new_directorate'],$rows['functional'],$rows['structural'],$rows['kr_organisasi_bss'],$rows['level_gaji'],$rows['gaji_dasar_bss'],$rows['tbh_bss'],$rows['tunjab_bss']);
		}
		
		$fp = fopen('php://output', 'wb');
		foreach ($user_CSV as $line) {
			fputcsv($fp, $line, ',');
		}
		fclose($fp);
		
		exit;	
	}
	
    public function actionSendManualyMail(){
		//get esk data
		$data_esk = EskLists::find()->where('status = "published" AND updated_at > "2022-04-18 23:00:00"')->all();
		if(!empty($data_esk)){
			//inisialisasi data count 
			$countSuccess = 0;
			$countFailed = 0;
			$countAll = 0;
			$failed_array = array();
			foreach($data_esk as $data_esk){
				try {
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
					$subject = "[eSK] FYI Published of eSK Number ".$data_esk->number_esk."";
					$content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk, 'nama_pengirim' => $data_esk->decree_nama],true);
					Model::sendMailMultiple($to,$subject,$content);
						
					//send mail to atasan langsung
					$subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
					$datakaryawan = Employee::findOne(['nik' => $data_esk->nik]);
					$atasan		  = Employee::findOne(['nik' => $datakaryawan->nik_atasan]);
					$to 		  = $atasan->email;
					$content = $this->renderPartial('../../mail/mail-published-atasan-new',['data_esk' => $data_esk,'atasan' => $atasan],true);
					Model::sendMailOne($to,$subject,$content);
					
					$countSuccess = $countSuccess + 1;
				} catch(\Swift_TransportException $e) {
					$countFailed = $countFailed + 1;
					array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed publish because ".$e);
				} catch (\Exception $e){
					$countFailed = $countFailed + 1;
					array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed publish because ".$e);
				}
				$countAll = $countAll + 1;
			}
			
			//check failed
			if(!empty($failed_array)){
				$failed_data = "that is ".implode(", ",array_unique($failed_array));
			}else{
				$failed_data = "";
			}

			//set flash message 
			return 'Successfully publish ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data;
		
		}
		
		return "eSK data is empty!";
    }

    /**
     * Lists all EskLists models.
     * @return mixed
     */
    public function actionIndex()
    {   
		set_time_limit(0);
		ini_set('memory_limit', '3048M');
		
		$flag = yii::$app->request->get('flag');
		
		$isexport = false;
		if($flag == "export")
			$isexport = true;
		
        if(
            (!empty(yii::$app->request->get('start_date')) && !empty(yii::$app->request->get('end_date'))) 
            || !empty(yii::$app->request->get('tipe')) || !empty(yii::$app->request->get('esk_id')) 
            || !empty(yii::$app->request->get('status')) || !empty(yii::$app->request->get('nik'))
            || !empty(yii::$app->request->get('nama'))
        ){
            //filter
            $start_date = date("Y-m-d",strtotime(yii::$app->request->get('start_date')));
            $end_date = date("Y-m-d",strtotime(yii::$app->request->get('end_date')));
            $start_date_data = yii::$app->request->get('start_date');
            $end_date_data = yii::$app->request->get('end_date');
            $tipe = yii::$app->request->get('tipe');
            $id = yii::$app->request->get('esk_id');
            $status = yii::$app->request->get('status');
            $nik = yii::$app->request->get('nik');
            $nama = yii::$app->request->get('nama');

            $queryDate = (empty($start_date_data) && empty($end_date_data)) ? '' : "and (effective_esk_date between '".$start_date."' and '".$end_date."')";
            $queryTipe = (empty($tipe)) ? '' : "and tipe like '%".$tipe."%'";
            $queryID = (empty($id)) ? '' : "and id = '".$id."'";
            $queryStatus = (empty($status)) ? '' : "and tracking like '%".$status."%'";
            $queryNik = (empty($nik)) ? '' : "and nik like '%".$nik."%'";
            $queryNama = (empty($nama)) ? '' : "and nama like '%".$nama."%'";

            if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                $query = "nik <> '' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryStatus." ".$queryNik." ".$queryNama;
                $dataProvider = new ActiveDataProvider([
                    'query' => EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('id ASC'),
                ]);
				if($isexport)
					$dataExport = EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('created_at ASC')->all(); 
            
                    
            }else{
                //get area user
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                } else{
                    $user_area = "N/A";
                }
                
                $query = "nik <> '' and authority = '".$user_area."' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryStatus." ".$queryNik." ".$queryNama;
                $dataProvider = new ActiveDataProvider([
                    'query' => EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('id ASC'),
                ]);
				if($isexport)
					$dataExport = EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('created_at ASC')->all();
            }
        }else{
			if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                //tampilkan semuanya
                $searchModel = new EskListsSearch();
                $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
				
				if($isexport)
					$dataExport = EskLists::find()->where('status != "canceled"')->orderBy('created_at ASC')->all(); 
            }else{
                //get area user
				$old_directorate = [];
				if(Yii::$app->user->can('hcbp_account') || Yii::$app->user->can('hcm_view')){
					$modelAuth 	= new EskAuthHc();
					$dataAuth 	= EskAuthHc::findOne(['nik' => Yii::$app->user->identity->employee->nik]);
				}elseif(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                }else{
                    $user_area = "N/A";
                }
				
				if(!empty($dataAuth)) {
					$dataProvider = new ActiveDataProvider([
						'query' => EskLists::find()
									//->where(['authority' => $user_area])
									->where('status != "canceled"')
									->andWhere(['or',
										['in', 'new_directorate' , $modelAuth->getArrValue($dataAuth->new_directorate)],
										['in', 'old_directorate' , $modelAuth->getArrValue($dataAuth->old_directorate)],
										['in', 'new_area' , $modelAuth->getArrValue($dataAuth->new_area)],
										['in', 'old_area' , $modelAuth->getArrValue($dataAuth->old_area)],
										])
									->orderBy('id DESC'),
					]);
				} else {
					return $this->render('error-setting-view', [
					]);
				}
					
				if($isexport)
					$dataExport = EskLists::find()
									//->where(['authority' => $user_area])
									->andWhere('status != "canceled"')
									->andWhere(['in', 'old_directorate' , $old_directorate])
									->orderBy('created_at ASC')
									->all(); 
            }

            $start_date_data = "";
            $end_date_data = "";
            $tipe = "";
            $id = "";
            $status = "";
            $nik = "";
            $nama = "";
        }
      
        //fungsi export data 
        if(!empty(yii::$app->request->get('flag')) && yii::$app->request->get('flag') == "export"){

            if(!empty($dataExport)){
                $this->exportDataTypeTwo($dataExport);
            }else{
                Yii::$app->session->setFlash('error', "Failed export, data is empty!");
            }
            
        }

        return $this->render('index', [
            'start_date' => $start_date_data,
            'end_date' => $end_date_data,
            'tipe' => $tipe,
            'id_esk' => $id,
            'status' => $status,
            'nik' => $nik,
            'nama' => $nama,
            'dataProvider' => $dataProvider,
        ]);
    }
	
	public function actionIndexRegenerate()
    {   
		set_time_limit(0);
		ini_set('memory_limit', '3048M');
		
		$flag = yii::$app->request->get('flag');
		
		$isexport = false;
		if($flag == "export")
			$isexport = true;
		
        if(
            (!empty(yii::$app->request->get('start_date')) && !empty(yii::$app->request->get('end_date'))) 
            || !empty(yii::$app->request->get('tipe')) || !empty(yii::$app->request->get('id')) 
            || !empty(yii::$app->request->get('status')) || !empty(yii::$app->request->get('nik'))
            || !empty(yii::$app->request->get('nama'))
        ){
            //filter
            $start_date = date("Y-m-d",strtotime(yii::$app->request->get('start_date')));
            $end_date = date("Y-m-d",strtotime(yii::$app->request->get('end_date')));
            $start_date_data = yii::$app->request->get('start_date');
            $end_date_data = yii::$app->request->get('end_date');
            $tipe = yii::$app->request->get('tipe');
            $id = yii::$app->request->get('id');
            $status = yii::$app->request->get('status');
            $nik = yii::$app->request->get('nik');
            $nama = yii::$app->request->get('nama');

            $queryDate = (empty($start_date_data) && empty($end_date_data)) ? '' : "and (effective_esk_date between '".$start_date."' and '".$end_date."')";
            $queryTipe = (empty($tipe)) ? '' : "and tipe like '%".$tipe."%'";
            $queryID = (empty($id)) ? '' : "and id = '".$id."'";
            $queryStatus = (empty($status)) ? '' : "and tracking like '%".$status."%'";
            $queryNik = (empty($nik)) ? '' : "and nik like '%".$nik."%'";
            $queryNama = (empty($nama)) ? '' : "and nama like '%".$nama."%'";

            if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                $query = "nik <> '' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryStatus." ".$queryNik." ".$queryNama;
                $dataProvider = new ActiveDataProvider([
                    'query' => EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('id ASC'),
                ]);
				if($isexport)
					$dataExport = EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('created_at ASC')->all(); 
            }else{
                //get area user
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                } else{
                    $user_area = "N/A";
                }
                
                $query = "nik <> '' and authority = '".$user_area."' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryStatus." ".$queryNik." ".$queryNama;
                $dataProvider = new ActiveDataProvider([
                    'query' => EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('id ASC'),
                ]);
				if($isexport)
					$dataExport = EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('created_at ASC')->all();
            }
        }else{
			if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                //tampilkan semuanya
                $searchModel = new EskListsSearch();
                $dataProvider = $searchModel->searchRegenerate(Yii::$app->request->queryParams);

				if($isexport)
					$dataExport = EskLists::find()->where('status != "canceled"')->orderBy('created_at ASC')->all(); 
            }else{
                //get area user
				$old_directorate = [];
				if(Yii::$app->user->can('hcbp_account') || Yii::$app->user->can('hcm_view')){
					$modelAuth 	= new EskAuthHc();
					$dataAuth 	= EskAuthHc::findOne(['nik' => Yii::$app->user->identity->employee->nik]);
				}elseif(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                }else{
                    $user_area = "N/A";
                }
				
				if(!empty($dataAuth)) {
					$dataProvider = new ActiveDataProvider([
						'query' => EskLists::find()
									//->where(['authority' => $user_area])
									->where('status != "canceled"')
									->andWhere(['or',
										['in', 'new_directorate' , $modelAuth->getArrValue($dataAuth->new_directorate)],
										['in', 'old_directorate' , $modelAuth->getArrValue($dataAuth->old_directorate)],
										['in', 'new_area' , $modelAuth->getArrValue($dataAuth->new_area)],
										['in', 'old_area' , $modelAuth->getArrValue($dataAuth->old_area)],
										])
									->orderBy('id DESC'),
					]);
				} else {
					return $this->render('error-setting-view', [
					]);
				}
					
				if($isexport)
					$dataExport = EskLists::find()
									//->where(['authority' => $user_area])
									->andWhere('status != "canceled"')
									->andWhere(['in', 'old_directorate' , $old_directorate])
									->orderBy('created_at ASC')
									->all(); 
            }

            $start_date_data = "";
            $end_date_data = "";
            $tipe = "";
            $id = "";
            $status = "";
            $nik = "";
            $nama = "";
        }
         
        //fungsi export data 
        if(!empty(yii::$app->request->get('flag')) && yii::$app->request->get('flag') == "export"){
            if(!empty($dataExport)){                
                $this->exportDataTypeTwo($dataExport);
            }else{
                Yii::$app->session->setFlash('error', "Failed export, data is empty!");
            }
        }

        return $this->render('index', [
            'start_date' => $start_date_data,
            'end_date' => $end_date_data,
            'tipe' => $tipe,
            'id_esk' => $id,
            'status' => $status,
            'nik' => $nik,
            'nama' => $nama,
            'dataProvider' => $dataProvider,
        ]);
    }
	
	public function actionListEskDirectorate($data)
    {   
        if ( 
        (!empty(yii::$app->request->get('start_date')) && !empty(yii::$app->request->get('end_date'))) 
        || !empty(yii::$app->request->get('tipe')) || !empty(yii::$app->request->get('id')) 
        || !empty(yii::$app->request->get('status')) || !empty(yii::$app->request->get('nik'))
        || !empty(yii::$app->request->get('nama'))
        ){
            //filter
            $start_date = date("Y-m-d",strtotime(yii::$app->request->get('start_date')));
            $end_date = date("Y-m-d",strtotime(yii::$app->request->get('end_date')));
            $start_date_data = yii::$app->request->get('start_date');
            $end_date_data = yii::$app->request->get('end_date');
            $tipe = yii::$app->request->get('tipe');
            $id = yii::$app->request->get('id');
            $status = yii::$app->request->get('status');
            $nik = yii::$app->request->get('nik');
            $nama = yii::$app->request->get('nama');

            $queryDate = (empty($start_date_data) && empty($end_date_data)) ? '' : "and (effective_esk_date between '".$start_date."' and '".$end_date."')";
            $queryTipe = (empty($tipe)) ? '' : "and tipe like '%".$tipe."%'";
            $queryID = (empty($id)) ? '' : "and id = '".$id."'";
            $queryStatus = (empty($status)) ? '' : "and tracking like '%".$status."%'";
            $queryNik = (empty($nik)) ? '' : "and nik like '%".$nik."%'";
            $queryNama = (empty($nama)) ? '' : "and nama like '%".$nama."%'";

            if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                $query = "nik <> '' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryStatus." ".$queryNik." ".$queryNama;
                $dataProvider = new ActiveDataProvider([
                    'query' => EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('id ASC'),
                ]);
    
                $dataExport = EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('created_at ASC')->all(); 
            }else{
                //get area user
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                } else{
                    $user_area = "N/A";
                }
                
                $query = "nik <> '' and authority = '".$user_area."' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryStatus." ".$queryNik." ".$queryNama;
                $dataProvider = new ActiveDataProvider([
                    'query' => EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('id ASC'),
                ]);
                $dataExport = EskLists::find()->where($query)->andWhere('status != "canceled"')->orderBy('created_at ASC')->all();
            }
        }else{
            if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                //tampilkan semuanya
                $searchModel = new EskListsSearch();
				$searchModel->new_directorate = $data;
                $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
                $dataExport = EskLists::find()->where('status != "canceled"')->orderBy('created_at ASC')->all(); 
            }else{
                //get area user
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                } else{
                    $user_area = "N/A";
                }
    
                $dataProvider = new ActiveDataProvider([
                    'query' => EskLists::find()->where(['authority' => $user_area])->andWhere('status != "canceled"')->orderBy('id ASC'),
                ]);
                $dataExport = EskLists::find()->where(['authority' => $user_area])->andWhere('status != "canceled"')->orderBy('created_at ASC')->all(); 
            }

            $start_date_data = "";
            $end_date_data = "";
            $tipe = "";
            $id = "";
            $status = "";
            $nik = "";
            $nama = "";
        }
         
        //fungsi export data 
        if(!empty(yii::$app->request->get('flag')) && yii::$app->request->get('flag') == "export"){
            if(!empty($dataExport)){
                $this->exportDataTypeThree($dataExport);
            }else{
                Yii::$app->session->setFlash('error', "Failed export, data is empty!");
            }
        }

        return $this->render('index_directorate', [
            'start_date' => $start_date_data,
            'end_date' => $end_date_data,
            'tipe' => $tipe,
            'id_esk' => $id,
            'status' => $status,
            'nik' => $nik,
            'nama' => $nama,
            'dataProvider' => $dataProvider,
        ]);
    }
	
    public function actionApproval_old()
    {   
        if (Yii::$app->request->post()) {
           $request = Yii::$app->request->post();

           //inisialisasi data count 
            $countSuccess = 0;
            $countFailed = 0;
            $countAll = 0;
            $failed_array = array();

           //get data generate
           $id_esk_cancel = explode(",",$request['id_esk_cancel']);
           foreach($id_esk_cancel as $id_esk){
                $data_update = Model::WorkFlowStatus("cancel", '', $id_esk);
                $data_esk = EskLists::findOne($id_esk);
                $data_esk->status = $data_update['status'];
                $data_esk->tracking = $data_update['tracking'];

                if($data_esk->save()) {
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Data with ID eSK ".$id_esk);

                    // procedure rollback ebs sprint 4
                    $model = EskLists::findOne($id_esk);
                    $sqllist = 'SELECT * FROM esklist_group_reason_v WHERE id = '.$id_esk.'';
                    $data_esk_ebs = Yii::$app->db->createCommand($sqllist)->queryOne();
                    if($data_esk_ebs['sync_status'] == '1'){

                	// faqih : add param p_new_position_id 08/07/2024
                        $sql = "
                        declare
                            v_message                      VARCHAR2(2000);
                            v_result                       VARCHAR2(200);
                        begin
                            TSEL_HC_ESK.process_rollback_esk (
                                    p_effective_esk_date => to_date(:effective_esk_date,'YYYY-MM-DD'),
                                    p_esk_id             => :esk_id,
                                    p_no_esk             => :esk_number,
                                    p_nik                => :nik,
                                    p_new_nik            => :new_nik,
                                    p_tipe               => :tipe,
                                    p_about_esk          => :about_esk,
                                    p_group              => :group,
                                    p_reason             => :reason,
                                    p_new_position_id	 => :new_position_id,
                                    p_new_position       => :new_position,
                                    p_new_organization   => :new_organization,
                                    p_new_grade          => :new_grade,
                                    p_nik_supervisor     => :nik_new_atasan,
                                    p_new_title          => :new_title,
                                    p_new_bp             => :new_bp,
                                    p_lvl_gaji           => :level_gaji,  
                                    p_period             => :period,
                                    p_dpe_length         => :dpe_length,
                                    p_dpe_unit           => :dpe_unit,
                                    p_gaji_dasar_nss     => to_number(:gaji_dasar_nss),
                                    p_tunj_hotskill      => to_number(:tunjangan_hot_skill),
                                    p_tunj_aktualisasi   => to_number(:tunjangan_aktualisasi), 
                                    p_login_person_nik   => :login_nik,
                                    p_leaving_reason     => :leaving_reason,
                                    p_statement_date     => to_date(:notification_date,'YYYY-MM-DD'),
                                    p_message            => :message,
                                    p_result             => :result
                                );
                        
                            dbms_output.put_line( 'v_message ' || v_message);
                            dbms_output.put_line( 'v_result ' || v_result);
                        end;
                        ";

                        $message = "";
                        $result = "";

                        $conn = Yii::$app->dbOra;
                        $command = $conn->createCommand(trim(preg_replace('/\s\s+/', ' ', $sql)))
                        ->bindValue(":effective_esk_date", $data_esk_ebs['effective_esk_date'])
                        ->bindValue(":esk_id", $data_esk_ebs['id'])
                        ->bindValue(":esk_number", $data_esk_ebs['number_esk'])
                        ->bindValue(":nik", $data_esk_ebs['nik']) 
                        ->bindValue(":new_nik", $data_esk_ebs['new_nik'])
                        ->bindValue(":tipe", $data_esk_ebs['tipe'])
                        ->bindValue(":about_esk", $data_esk_ebs['about_esk'])
                        ->bindValue(":group", $data_esk_ebs['groups_reason'])
                        ->bindValue(":reason", $data_esk_ebs['reason'])
                        ->bindValue(":new_position_id", $data_esk['new_position_id'])
                        ->bindValue(":new_position", $new_position_data['position_code'].".".$new_position_data['nama'])
                        ->bindValue(":new_organization", $data_esk_ebs['new_organization'])
                        ->bindValue(":new_grade", $data_esk_ebs['grade'])
                        ->bindValue(":nik_new_atasan", $data_esk_ebs['nik_new_atasan'])
                        ->bindValue(":new_title", $data_esk_ebs['new_title'])
                        ->bindValue(":new_bp", $data_esk_ebs['level_band'])
                        ->bindValue(":level_gaji", $data_esk_ebs['level_gaji'])
                        ->bindValue(":period", $data_esk_ebs['periode'])
                        ->bindValue(":dpe_length", $data_esk_ebs['dpe_length'])
                        ->bindValue(":dpe_unit", $data_esk_ebs['dpe_unit'])
                        ->bindValue(":gaji_dasar_nss", empty($data_esk_ebs['gaji_dasar_nss']) ? 0 : $data_esk_ebs['gaji_dasar_nss'])
                        ->bindValue(":tunjangan_hot_skill", empty($data_esk_ebs['tunjangan_hot_skill']) ? 0 : $data_esk_ebs['tunjangan_hot_skill'])
                        ->bindValue(":tunjangan_aktualisasi", empty($data_esk_ebs['tunjangan_aktualisasi']) ? 0 : $data_esk_ebs['tunjangan_aktualisasi'])
                        ->bindValue(":login_nik", empty(Yii::$app->user->identity->nik) ? 0 : Yii::$app->user->identity->nik)
                        ->bindValue(":leaving_reason", $data_esk_ebs['leaving_reason'])
                        ->bindValue(":notification_date", $data_esk_ebs['notification_date'])
                        ->bindParam(":message", $message,PDO::PARAM_STR,1000)
                        ->bindParam(":result", $result,PDO::PARAM_STR,50)
                        ->execute();
            
                        if(!empty($result) && $result == "SUCCESS"){
                            //set success count
                            $countSuccess++;
            
                            //logging data
                            $warning_label = empty($message) ? "" : " with note ".$message;
                            Model::saveLog(Yii::$app->user->identity->username, "HC Staffing Rollback sync eSK data with ID ".$data_esk['id'].$warning_label );
                            
                            array_push($success_array,"data eSK ".$data_esk['nik']."/".$data_esk['nama']."/".$data_esk['tipe']." success Rollback sync eSK".$warning_label." \r\n");

                            //update eSK Data
                            $model->cancel_synced_date   = date("Y-m-d H:i:s");
                        }else{
                            $model->cancel_sync_result = "failed Rollback sync eSK because ".$message;
                            //set failed count
                            $countFailed++;
                            array_push($failed_array,"data eSK ".$data_esk['nik']."/".$data_esk['nama']."/".$data_esk['tipe']." failed sync eSK because ".$message."\r\n");
                            Model::saveLog(Yii::$app->user->identity->username, "Failed Rollback sync eSK data for ID ".$data_esk['id']." because ".$message);
                        }

                    }
                    $model->save();
                //

                    // $countSuccess++;
                } else {
                    $countFailed = $countFailed + 1;

                    //logging data
                    $error = implode(",",$data_esk->getErrorSummary(true));
                    array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed because ".$error);
                }

                //count iteration
                $countAll = $countAll + 1;
            }
            
            if(!empty($failed_array)){
                $failed_data = "that is ".implode(", ",array_unique($failed_array));
            }else{
                $failed_data = "";
            }

            //send flash message berisi count success, count failed dan count all
            Yii::$app->session->setFlash('info', 'Successfully canceled ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data); 
            return $this->redirect(['/esk-lists/approval']);
        }

        $searchModel = new EskListsSearch();
        $dataProvider = $searchModel->approvalCancelLists();

        return $this->render('approval', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    // add by faqih sprint 4
    public function actionApproval()
    {   
        if (Yii::$app->request->post()) {
           $request = Yii::$app->request->post();

           //inisialisasi data count 
            $countSuccess = 0;
            $countFailed = 0;
            $countAll = 0;
            $failed_array = array();
            $success_array = array();

           //get data generate
           $id_esk_cancel = explode(",",$request['id_esk_cancel']);
           foreach($id_esk_cancel as $id_esk){
                $data_update = Model::WorkFlowStatus("cancel", '', $id_esk);
                $data_esk = EskLists::findOne($id_esk);
                $data_esk->status = $data_update['status'];
                $data_esk->tracking = $data_update['tracking'];
                
                // procedure rollback ebs sprint 4
                $cek_template = EskTemplateMaster::find()->where(['code_template' => $data_esk['code_template']])->one();
                $cekgroup_reason = EskGroupReasonData::findOne($cek_template['id_reason']);
                if(!empty($cekgroup_reason)){
                    // print_r($cekgroup_reason->group);
                    if(strpos($cekgroup_reason->group, 'Amandemen') !== false || strpos($cekgroup_reason->group, 'Probation to Permanent') !== false  || strpos($cekgroup_reason->group, 'Alih Status') !== false){
                        // print_r('ini');
                        if($data_esk->save()) {
                            //logging data
                            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Data with ID eSK ".$id_esk);
        
                            $countSuccess++;
                        } else {
                            $countFailed = $countFailed + 1;
        
                            //logging data
                            $error = implode(",",$data_esk->getErrorSummary(true));
                            array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed because ".$error);
                        }
                    }else{
                        // print_r('itu');
                        $model = EskLists::findOne($id_esk);
                        $sqllist = 'SELECT * FROM esklist_group_reason_v WHERE id = '.$id_esk.'';
                        $data_esk_ebs = Yii::$app->db->createCommand($sqllist)->queryOne();
                        if($data_esk_ebs['sync_status'] == '1'){


                    	// faqih : add param p_new_position_id 08/07/2024
                            $sql = "
                            declare
                                v_message                      VARCHAR2(2000);
                                v_result                       VARCHAR2(200);
                            begin
                                TSEL_HC_ESK.process_rollback_esk (
                                        p_effective_esk_date => to_date(:effective_esk_date,'YYYY-MM-DD'),
                                        p_esk_id             => :esk_id,
                                        p_no_esk             => :esk_number,
                                        p_nik                => :nik,
                                        p_new_nik            => :new_nik,
                                        p_tipe               => :tipe,
                                        p_about_esk          => :about_esk,
                                        p_group              => :group,
                                        p_reason             => :reason,
                                        p_new_position_id	 => :new_position_id,
                                        p_new_position       => :new_position,
                                        p_new_organization   => :new_organization,
                                        p_new_grade          => :new_grade,
                                        p_nik_supervisor     => :nik_new_atasan,
                                        p_new_title          => :new_title,
                                        p_new_bp             => :new_bp,
                                        p_lvl_gaji           => :level_gaji,  
                                        p_period             => :period,
                                        p_dpe_length         => :dpe_length,
                                        p_dpe_unit           => :dpe_unit,
                                        p_gaji_dasar_nss     => to_number(:gaji_dasar_nss),
                                        p_tunj_hotskill      => to_number(:tunjangan_hot_skill),
                                        p_tunj_aktualisasi   => to_number(:tunjangan_aktualisasi), 
                                        p_login_person_nik   => :login_nik,
                                        p_leaving_reason     => :leaving_reason,
                                        p_statement_date     => to_date(:notification_date,'YYYY-MM-DD'),
                                        p_message            => :message,
                                        p_result             => :result
                                    );
                            
                                dbms_output.put_line( 'v_message ' || v_message);
                                dbms_output.put_line( 'v_result ' || v_result);
                            end;
                            ";

                            $message = "";
                            $result = "";

                            $conn = Yii::$app->dbOra;
                            $command = $conn->createCommand(trim(preg_replace('/\s\s+/', ' ', $sql)))
                            ->bindValue(":effective_esk_date", $data_esk_ebs['effective_esk_date'])
                            ->bindValue(":esk_id", $data_esk_ebs['id'])
                            ->bindValue(":esk_number", $data_esk_ebs['number_esk'])
                            ->bindValue(":nik", $data_esk_ebs['nik']) 
                            ->bindValue(":new_nik", $data_esk_ebs['new_nik'])
                            ->bindValue(":tipe", $data_esk_ebs['tipe'])
                            ->bindValue(":about_esk", $data_esk_ebs['about_esk'])
                            ->bindValue(":group", $data_esk_ebs['groups_reason'])
                            ->bindValue(":reason", $data_esk_ebs['reason'])
                            ->bindValue(":new_position_id", $data_esk['new_position_id'])
                            ->bindValue(":new_position", $new_position_data['position_code'].".".$new_position_data['nama'])
                            ->bindValue(":new_organization", $data_esk_ebs['new_organization'])
                            ->bindValue(":new_grade", $data_esk_ebs['grade'])
                            ->bindValue(":nik_new_atasan", $data_esk_ebs['nik_new_atasan'])
                            ->bindValue(":new_title", $data_esk_ebs['new_title'])
                            ->bindValue(":new_bp", $data_esk_ebs['level_band'])
                            ->bindValue(":level_gaji", $data_esk_ebs['level_gaji'])
                            ->bindValue(":period", $data_esk_ebs['periode'])
                            ->bindValue(":dpe_length", $data_esk_ebs['dpe_length'])
                            ->bindValue(":dpe_unit", $data_esk_ebs['dpe_unit'])
                            ->bindValue(":gaji_dasar_nss", empty($data_esk_ebs['gaji_dasar_nss']) ? 0 : $data_esk_ebs['gaji_dasar_nss'])
                            ->bindValue(":tunjangan_hot_skill", empty($data_esk_ebs['tunjangan_hot_skill']) ? 0 : $data_esk_ebs['tunjangan_hot_skill'])
                            ->bindValue(":tunjangan_aktualisasi", empty($data_esk_ebs['tunjangan_aktualisasi']) ? 0 : $data_esk_ebs['tunjangan_aktualisasi'])
                            ->bindValue(":login_nik", empty(Yii::$app->user->identity->nik) ? 0 : Yii::$app->user->identity->nik)
                            ->bindValue(":leaving_reason", $data_esk_ebs['leaving_reason'])
                            ->bindValue(":notification_date", $data_esk_ebs['notification_date'])
                            ->bindParam(":message", $message,PDO::PARAM_STR,1000)
                            ->bindParam(":result", $result,PDO::PARAM_STR,50)
                            ->execute();
                		// print_r($result);
                		// die();
                            if($result == "SUCCESS" && $data_esk->save()){
                            	// $data_esk->save();
                                //logging data
                                Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Data with ID eSK ".$id_esk);

                                //set success count
                                $countSuccess++;
                
                                //logging data
                                $warning_label = empty($message) ? "" : " with note ".$message;
                                Model::saveLog(Yii::$app->user->identity->username, "HC Staffing Rollback sync eSK data with ID ".$data_esk['id'].$warning_label );
                                
                                array_push($success_array,"data eSK ".$data_esk['nik']."/".$data_esk['nama']."/".$data_esk['tipe']." success Rollback sync eSK".$warning_label." \r\n");

                                //update eSK Data
                                $model->cancel_synced_date   = date("Y-m-d H:i:s");
                            }else{
                                $model->cancel_sync_result = "failed Rollback sync eSK because ".$message;
                                //set failed count
                                $countFailed++;

                                //logging data
                                $error = implode(",",$data_esk->getErrorSummary(true));
                                array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed Rollback sync eSK because ".$error);

                                // array_push($failed_array,"data eSK ".$data_esk['nik']."/".$data_esk['nama']."/".$data_esk['tipe']." failed sync eSK because ".$message."\r\n");
                                Model::saveLog(Yii::$app->user->identity->username, "Failed Rollback sync eSK data for ID ".$data_esk['id']." because ".$message);
                            }
                            $model->save();
                        }else{
                        	if($data_esk->save()) {
                            //logging data
	                            Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Data with ID eSK ".$id_esk);
	        
	                            $countSuccess++;
	                        } else {
	                            $countFailed = $countFailed + 1;
	        
	                            //logging data
	                            $error = implode(",",$data_esk->getErrorSummary(true));
	                            array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed because ".$error);
	                        }
                        }
                    }
                }else{
                    if($data_esk->save()) {
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Delete eSK Data with ID eSK ".$id_esk);
    
                        $countSuccess++;
                    } else {
                        $countFailed = $countFailed + 1;
    
                        //logging data
                        $error = implode(",",$data_esk->getErrorSummary(true));
                        array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed because ".$error);
                    }
                }

                //count iteration
                $countAll = $countAll + 1;
            }
            // die();
            if(!empty($failed_array)){
                $failed_data = "that is ".implode(", ",array_unique($failed_array));
            }else{
                $failed_data = "";
            }

            //send flash message berisi count success, count failed dan count all
            Yii::$app->session->setFlash('info', 'Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data !'); 
            return $this->redirect(['/esk-lists/approval']);
        }

        $searchModel = new EskListsSearch();
        $dataProvider = $searchModel->approvalCancelLists();

        return $this->render('approval', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
    // end

    public function actionCancelLists()
    {   
        $searchModel = new EskListsSearch();
        $dataProvider = $searchModel->cancelLists();

        return $this->render('cancel', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionPreview($id,$flag = null)
    {   
        $model = $this->findModel($id);
        //check status esk 
        /*if($model->status == "published" || $model->status == "approved"){
            $file_name = $model->file_name;
        }else{
            $file_name = "";
        }*/
        $file_name = "";
        $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"preview");

        return $this->render('preview', [
            'model' => $model,
            'content' => $all_content,
            "flag" => $flag
        ]);
    }

    public function actionPrint()
    {   
		set_time_limit(0);
		ini_set('memory_limit', '9048M');
		
        $id = yii::$app->request->get('id');
        $flag = yii::$app->request->get('flag');
        $model = $this->findModel($id);

        //logging data
        Model::saveLog(Yii::$app->user->identity->username, "Print eSK with ID ".$model->id);

        $file_name = "";
        
        if($flag == 1){
            //get data esk_template_master
            $esk_template = EskTemplateMaster::find()->where(['code_template' => $model->code_template])->one();
            $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"print","1");

            if(empty($esk_template)){
                //print default tanpa page break dan footer
                $pdf = new Pdf([
                    'mode' => Pdf::MODE_UTF8, 
                    'format' => Pdf::FORMAT_A4, 
                    //'format' => [330, 300],		
					'orientation' => Pdf::ORIENT_PORTRAIT, 
                    'defaultFontSize' => 8,
                    'marginLeft' => 16,
                    'marginRight' => 16,
                    'marginTop' => 20,
                    'marginBottom' =>7,
                    'marginHeader' => 8,
                    'marginFooter' => 8,
                    'filename' => "Surat Keputusan Nomor: ".$model->number_esk." tentang ".$model->about_esk.".pdf",
                    'destination' => Pdf::DEST_DOWNLOAD, //Pdf::DEST_DOWNLOAD
                    'content' => $all_content,   
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',  
                ]);

                return $pdf->render();
            }else{
                //cek page break content 
                if($esk_template->page_break_content != 0 || $esk_template->page_break_content != "0"){
                    //get page break data
                    $data_content = Model::setPageBreak($esk_template->id,$esk_template->page_break_content,$all_content);
    
                    if($data_content['is_pagebreak'] == 1){
                        //print dengan page break dan footer
                        $pdf = new Pdf([
                            'mode' => Pdf::MODE_UTF8, 
                            'format' => Pdf::FORMAT_A4, 
                            //'format' => [330, 300],		
							'orientation' => Pdf::ORIENT_PORTRAIT, 
                            'defaultFontSize' => 8,
                            'marginLeft' => 16,
                            'marginRight' => 16,
                            'marginTop' => 20,
                            'marginBottom' =>7,
                            'marginHeader' => 8,
                            'marginFooter' => 8,
                            'filename' => "Surat Keputusan Nomor: ".$model->number_esk." tentang ".$model->about_esk.".pdf",
                            'destination' => Pdf::DEST_DOWNLOAD, //Pdf::DEST_DOWNLOAD
                            'content' => str_replace("font-size:8pt","font-size:9pt",$data_content['content']),   
                            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',  
                            'cssInline' => '
                                @media print{
                                    .page-break{display: block;page-break-before: always;}
                                }
                            ',
                            'methods' => [ 
                                'SetHtmlFooter'=>['
                                <table width="100%" style="font-family:arial;font-size:7pt;">
                                    <tr>
                                        <td width="40%">Halaman {PAGENO}/{nbpg}</td>
                                        <td width="60%" style="text-align: right;">eSK Nomor: '.$model->number_esk.'</td>
                                    </tr>
                                </table>
                                '],
                            ]
                        ]);
        
                        return $pdf->render();
                    }else{
                        //print default tanpa page break dan footer
                        $pdf = new Pdf([
                            'mode' => Pdf::MODE_UTF8, 
                            'format' => Pdf::FORMAT_A4, 
                            //'format' => [330, 300],		
							'orientation' => Pdf::ORIENT_PORTRAIT, 
                            'defaultFontSize' => 8,
                            'marginLeft' => 16,
                            'marginRight' => 16,
                            'marginTop' => 20,
                            'marginBottom' =>7,
                            'marginHeader' => 8,
                            'marginFooter' => 8,
                            'filename' => "Surat Keputusan Nomor: ".$model->number_esk." tentang ".$model->about_esk.".pdf",
                            'destination' => Pdf::DEST_DOWNLOAD, //Pdf::DEST_DOWNLOAD
                            'content' => $all_content,   
                            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',  
                        ]);

                        return $pdf->render();
                    }
                }else{
                    //print default tanpa page break dan footer
                    $pdf = new Pdf([
                        'mode' => Pdf::MODE_UTF8, 
                        'format' => Pdf::FORMAT_A4,
						//'format' => [330, 300],		
                        'orientation' => Pdf::ORIENT_PORTRAIT, 
                        'defaultFontSize' => 8,
                        'marginLeft' => 16,
                        'marginRight' => 16,
                        'marginTop' => 20,
                        'marginBottom' =>7,
                        'marginHeader' => 8,
                        'marginFooter' => 8,
                        'filename' => "Surat Keputusan Nomor: ".$model->number_esk." tentang ".$model->about_esk.".pdf",
                        'destination' => Pdf::DEST_DOWNLOAD, //Pdf::DEST_DOWNLOAD
                        'content' => $all_content,   
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',  
                    ]);

                    return $pdf->render();
                }
            }
        }else{
            $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"preview","1");

            return $this->renderPartial('//site/print_plain', [
                'content' => $all_content,
            ]);
        }
    }

    public function actionCheckPublish($id){
		set_time_limit(0);
		ini_set('memory_limit', '9048M');
		
        $ids = explode(",",$id);
        $esk_data = EskLists::find()->where(['in','id',$ids])->all();
        $error = 0;

        foreach($esk_data as $esk){
            if($esk->status == "canceled" || $esk->status == "processed" || $esk->status == "published" || $esk->status == "rejected" || $esk->status == "waiting_canceled"){
               $error++; 
            }
        }
		
		//var_dump($error);exit;
        $data = array(
            "error" => $error,
            "id" => $id
        );

        return json_encode($data);
    }
	
	 public function actionCheckPublishNew($id){
		set_time_limit(0);
		ini_set('memory_limit', '9048M');
		
        $ids = explode(",",$id);
        $esk_data = EskLists::find()->where(['in','id',$ids])->all();
        $error = 0;

        foreach($esk_data as $esk){
            if($esk->status == "canceled" || $esk->status == "processed" || $esk->status == "published" || $esk->status == "rejected" || $esk->status == "waiting_canceled"){
               $error++; 
            }
        }
		
		//var_dump($error);exit;
        $data = array(
            "error" => $error,
            "id" => $id
        );

        //return json_encode($data);
		
		return $this->redirect(array('staffing-lists/index'));
    }
	
    public function actionDetail($id,$flag = null){
        $model = EskLists::find()->where(['id' => $id])->one();
        $approval = EskApprovalLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->all();
        $count_app = EskApprovalLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->count();
        $workflow = EskWorkflowLists::find()->where(['id_esk' => $id])->orderBy('created_at ASC')->all();
        $ack = EskAcknowledgeLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->all();
        $count_ack = EskAcknowledgeLists::find()->where(['id_esk' => $id])->orderBy('sequence ASC')->count();
        $last_ack = EskAcknowledgeLists::find()->select('MAX(sequence) as sequence')->where(['id_esk' => $id])->one();

        return $this->render('detail', [
            'model' => $model,
            'approval' => $approval,
            'acknowledge_data' => $ack,
            'count_ack' => $count_ack,
            'count_app' => $count_app,
            "workflow" => $workflow,
            "flag" => $flag,
            "last_ack" => $last_ack->sequence
        ]);
    }

    public function actionRegenerate($id){
        //save datanya
        $esk = EskLists::findOne($id);    

        //get data terkait salary seperti gaji dasar dan tunjangan lainnya
        $salary = Model::getSalaryData($esk->new_bi,$esk->new_bp);
        $esk->gaji_dasar = $salary['gaji_dasar'];
        $esk->tunjangan_biaya_hidup = $salary['tunjangan_biaya_hidup'];
        $esk->tunjangan_jabatan = $salary['tunjangan_jabatan'];
        $esk->tunjangan_fungsional = $salary['tunjangan_fungsional'];
        $esk->tunjangan_rekomposisi = $salary['tunjangan_rekomposisi'];
        if($esk->save()){
            $esk2 = EskLists::findOne($id); 
            $esk2->content_esk = Model::regenerateEsk($id);
            $esk2->save();
            
            Yii::$app->session->setFlash('success', "eSK template successfully regenerated!");
        }else{
            Yii::$app->session->setFlash('error', "eSK template failed regenerated!");
        }

        return $this->redirect(array('esk-lists/preview', 'id' => $id));
    }

    public function actionRegenerateAll(){
		set_time_limit(0);
		ini_set('memory_limit', '9048M');
		
        $id_esk_data = yii::$app->request->get('id_esk');
		$id_esk = explode(",",$id_esk_data);
		
		//inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();
        
        foreach($id_esk as $id){
			//save datanya
			$esk = EskLists::findOne($id);    

			//get data terkait salary seperti gaji dasar dan tunjangan lainnya
			$salary = Model::getSalaryData($esk->new_bi,$esk->new_bp);
			$esk->gaji_dasar = $salary['gaji_dasar'];
			$esk->tunjangan_biaya_hidup = $salary['tunjangan_biaya_hidup'];
			$esk->tunjangan_jabatan = $salary['tunjangan_jabatan'];
			$esk->tunjangan_fungsional = $salary['tunjangan_fungsional'];
			$esk->tunjangan_rekomposisi = $salary['tunjangan_rekomposisi'];
			if($esk->save()){
				$esk2 = EskLists::findOne($id); 
				$esk2->content_esk = Model::regenerateEsk($id);
				$esk2->save();
				
				//set success count
                $countSuccess = $countSuccess + 1;
			}else{
				//set failed count
                $countFailed = $countFailed + 1;

                //logging data
                $error = implode(",",$esk->getErrorSummary(true));
                Model::saveLog(Yii::$app->user->identity->username, "Failed regenerate eSK data for ID ".$esk->id." because ".$error);
                array_push($failed_array,"data eSK ".$esk->nik."/".$esk->nama."/".$esk->tipe." failed regenerate because ".$error);
				
				Yii::$app->session->setFlash('error', "eSK template failed regenerated!");
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
        Yii::$app->session->setFlash('info', 'Successfully regenerate' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Failed ' . $countFailed . ' data '.$failed_data);

		return $this->redirect(['index']);
    }		

    public function actionQrcode($text){
        $qr = Yii::$app->get('qr');

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', $qr->getContentType());

        return $qr
            ->setText($text)
            ->writeString();
    }

    public function actionReassignack($id,$flag_staffing = null){
        //get data acknowledge
        $model = EskAcknowledgeLists::findOne($id);
        $id_esk = $model->id_esk;
        $sequence = $model->sequence;

        //get data esk lists
        $data_esk = EskLists::findOne($id_esk);
        $flag_ack_seq = $data_esk->flag_ack_seq;

        if (Yii::$app->request->post()) {
            $request =  Yii::$app->request->post();
            $emp = $request['reassign-app-ack'];

            //check apakah update atau tidak 
            if(strpos($model->ack_nik, $emp) === false && strpos($emp, "(") === false){
                //cari data by nik 
                $dataEmp = Employee::find()->where(['nik' => $emp])->one();
                if(!empty($dataEmp)){
                    $model->ack_nik = $emp;
                    $model->ack_name = $dataEmp->nama;
                    $model->ack_mail = $dataEmp->email;
                    $model->ack_title = $dataEmp->title;
                    if($model->save()){
                        if($data_esk->status == "delivered" && $sequence == $flag_ack_seq){
                            //change new status
                            $data_esk->tracking = "Delivered to ".$dataEmp->title." (".$dataEmp->nama.")";
                            $data_esk->save();
                        }

                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Reassign acknowledge user of eSK Lists with ID User ".$model->id);

                        //set flash berhasil reassign
                        Yii::$app->session->setFlash('success', "Acknowledge User of eSK data successfully reassign!");
                    }else{
                        //logging data
                        $error = implode(",",$model->getErrorSummary(true));
                        Model::saveLog(Yii::$app->user->identity->username, "Failed reassign acknowledge user of eSK data for ID User ".$model->id." because ".$error);
                        
                        //set flassh failed reassign
                        Yii::$app->session->setFlash('error', "Failed reassign acknowledge user of eSK, because ".$error);
                    }
                }else{
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Failed reassign acknowledge user of eSK data because employee data not found!");
                    Yii::$app->session->setFlash('error', "Failed reassign acknowledge user of eSK, employee data not found.");
                }
            }else{
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Failed reassign acknowledge user of eSK data because employee is same!");
                Yii::$app->session->setFlash('error', "Failed reassign acknowledge user of eSK, employee data is same data.");
            }
    
            //balik ke detail
            if(empty($flag_staffing)){
                return $this->redirect(['detail', 'id'=>$model->id_esk]);
            }else{
                return $this->redirect(['staffing-lists/detail', 'id'=>$model->id_esk]);
            }
        }

        return $this->renderAjax('reassignack', [
            'model' => $model
        ]);
    }

    public function actionReassignDeliver($id,$flag_staffing = null){
        //get data esk lists
        $model = EskLists::findOne($id);

        if (Yii::$app->request->post()) {
            $request =  Yii::$app->request->post();
            $emp = $request['reassign-app-deliver'];
            
            //check apakah update atau tidak 
            if(strpos($model->atasan_created, $emp) === false && strpos($emp, "(") === false){
                //cari data by nik 
                $dataEmp = Employee::find()->where(['nik' => $emp])->one();
                if(!empty($dataEmp)){
                    $model->atasan_created = $emp;
                    if($model->save()){
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Reassign deliver user of eSK Lists with ID eSK ".$model->id);
						
						if($model->status == "approved") {
							try{
									Yii::$app->mailer->compose('mail-reassign', ['esk' => $model, 'dataEmp' => $dataEmp])
									->setFrom('esk-application@hcm.telkomsel.co.id')
									->setTo($dataEmp->email)
									->setSubject('Deliver SK')
									->send();
								} catch(\Swift_TransportException $e){
							}
						}
						
                        //set flash berhasil reassign
                        Yii::$app->session->setFlash('success', "Deliver User of eSK data successfully reassign!");
                    }else{
                        //logging data
                        $error = implode(",",$model->getErrorSummary(true));
                        Model::saveLog(Yii::$app->user->identity->username, "Failed reassign deliver user of eSK data for ID eSK ".$model->id." because ".$error);
                        
                        //set flassh failed reassign
                        Yii::$app->session->setFlash('error', "Failed reassign deliver user of eSK, because ".$error);
                    }
                }else{
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Failed reassign deliver user of eSK data because employee data not found!");
                    Yii::$app->session->setFlash('error', "Failed reassign deliver user of eSK, employee data not found.");
                }
            }else{
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Failed reassign deliver user of eSK data because employee is same!");
                Yii::$app->session->setFlash('error', "Failed reassign deliver user of eSK, employee data is same with approval.");
            }
    
            //balik ke detail
            if(empty($flag_staffing)){
                return $this->redirect(['detail', 'id'=>$model->id]);
            }else{
                return $this->redirect(['staffing-lists/detail', 'id'=>$model->id]);
            }
        }

        return $this->renderAjax('reassign_deliver', [
            'model' => $model
        ]);
    }

    public function actionAddAck($id = null){
        $model = new EskAcknowledgeLists();

        if (Yii::$app->request->post()) {
            $request =  Yii::$app->request->post();
            $id_esk = $request['id'];
            $emp = $request['add-app-ack'];

            //cari data by nik 
            $dataEmp = Employee::find()->where(['nik' => $emp])->one();
            if(!empty($dataEmp)){
                $model->id_esk = $id_esk;
                $model->ack_nik = $emp;
                $model->ack_name = $dataEmp->nama;
                $model->ack_mail = $dataEmp->email;
                $model->ack_title = $dataEmp->title;
                
                //get sequence max
                $data_ack = EskAcknowledgeLists::find()->select('MAX(sequence) as sequence')->where(['id_esk' => $id_esk])->one();
                if(!empty($data_ack)){
                    $model->sequence = $data_ack->sequence + 1;
                }else{
                    $model->sequence = 1;
                }
                
                if($model->save()){
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Add acknowledge user of eSK Lists with ID User ".$model->id);

                    //set flash berhasil reassign
                    Yii::$app->session->setFlash('success', "Acknowledge User of eSK data successfully added!");
                }else{
                    //logging data
                    $error = implode(",",$model->getErrorSummary(true));
                    Model::saveLog(Yii::$app->user->identity->username, "Failed add acknowledge user of eSK data for ID User ".$model->id." because ".$error);
                    
                    //set flassh failed reassign
                    Yii::$app->session->setFlash('error', "Failed add acknowledge user of eSK, because ".$error);
                }
            }else{
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Failed add acknowledge user of eSK data because employee data not found!");
                Yii::$app->session->setFlash('error', "Failed add acknowledge user of eSK, employee data not found.");
            }
    
            //balik ke detail
            return $this->redirect(['detail', 'id'=>$id_esk]);
        }

        return $this->renderAjax('addack', [
            'model' => $model,
            'id' => $id
        ]);
    }

    public function actionDeleteAck($id){
        //get data acknowledge
        $model = EskAcknowledgeLists::findOne($id);
        $id_esk = $model->id_esk;
        $sequence = $model->sequence;

        //get data esk lists
        $data_esk = EskLists::findOne($id_esk);
        $flag_ack_seq = $data_esk->flag_ack_seq;

        if ($model->delete()) {
            //if($data_esk->status == "delivered" && $sequence == $flag_ack_seq){
			if($data_esk->status == "delivered"){
                //change new status
				$urutan = 1;
				$dataModel = EskAcknowledgeLists::findAll(['id_esk' => $model->id_esk]);
				
				foreach($dataModel as $dataAck) {
					$modelAck = EskAcknowledgeLists::findOne(['id' => $dataAck->id]);
					$modelAck->sequence = $urutan;
					$modelAck->save();
					
					$urutan = $urutan + 1;	
				}
                //$data_esk->flag_ack_seq = $model->next->sequence;
				//$data_esk->tracking = "Delivered to ".$model->next->ack_title." (".$model->next->ack_name.")";
                $lastDataAck 		= EskAcknowledgeLists::find()->where(['status' => 'pending'])->andWhere(['id_esk' => $model->id_esk])->orderBy(['sequence' => SORT_ASC ])->one();
				$data_esk->tracking = "Delivered to ".$lastDataAck->ack_title." (".$lastDataAck->ack_name.")";
				$data_esk->save();
            }

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Remove acknowledge user of eSK data with ID Ack ".$model->id);

            //set flash berhasil reassign
            Yii::$app->session->setFlash('success', "Acknowledge User of eSK data successfully removed!");
        }else{
            //logging data
            $error = implode(",",$model->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed remove acknowledge user of eSK data for ID User ".$model->id." because ".$error);
            
            //set flassh failed reassign
            Yii::$app->session->setFlash('error', "Failed removed of eSK acknowledge user, because ".$error);
        }

        //balik ke detail
        return $this->redirect(['detail', 'id'=>$id_esk]);
    }

    public function actionCronack(){
		set_time_limit(0);
		ini_set('memory_limit', '8000M');
		
        //get data flag_ack
        $flag_ack = EskFlagData::find()->one()->flag_ack;
        if($flag_ack == 1){
            //get data esk where status delivered
            $data_esk = EskLists::find()
            ->join('JOIN','esk_template_master','esk_lists.code_template = esk_template_master.code_template')
            ->where(['status' => 'delivered','flag_deliver_to' => '1'])->orderBy('id ASC')->all();
            if(!empty($data_esk)){
                foreach($data_esk as $esk){
                    //get data ack_lists
                    $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $esk->id, 'sequence' => $esk->flag_ack_seq])->one();
                    $max_sequence = EskAcknowledgeLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $esk->id])->one();

                    //check perbedaan tgl
                    $tgl_before = $esk->flag_ack_seq == 1 ? date("Y-m-d",strtotime($esk->updated_at)) : date("Y-m-d",strtotime($data_ack->prev->ack_at));
                    $today = date("Y-m-d");
                    $selisih = abs(strtotime($today) - strtotime($tgl_before));
                    $days = floor($selisih / (60*60*24));

                    if($days >= 2){
                        //check lagi apakah last approval
                        if($max_sequence->sequence == $esk->flag_ack_seq && $esk->status != 'published'){
                            //panggil method publish
                            $this->published($esk->id,$data_ack->id);
                        }else{
                            //panggil method ack
                            $this->acknowledge($esk->id,$data_ack->id);
                        }
                    }
                }
            }
        }
    }

    public function actionReassignApp($id, $flag_staffing = null){
        //get app data
        $model = EskApprovalLists::findOne($id);
        $id_esk = $model->id_esk;
        $sequence = $model->sequence;

        //get data esk lists
        $data_esk = EskLists::findOne($id_esk);
        $flag_app_seq = $data_esk->flag_approval_seq;

        if (Yii::$app->request->post()) {
            $request =  Yii::$app->request->post();
            $emp = $request['reassign-app-ack'];
            
			
            //check apakah update atau tidak 
            if(strpos($model->approval_nik, $emp) === false && strpos($emp, "(") === false){
                //cari data by nik 
                $dataEmp = Employee::find()->where(['nik' => $emp])->one();
				/*
				$dataDecree 			= EskDecreeBy::findOne(['decree_by' => $emp]);
				$data_esk->number_esk 	= substr($data_esk->number_esk, 0,12) . substr($dataDecree->number_esk, 8) . substr($data_esk->number_esk, 19,25);
				var_dump($data_esk->number_esk);exit;
				*/
				
                if(!empty($dataEmp)){
                    $model->approval_nik = $emp;
                    $model->approval_name = $dataEmp->nama;
                    $model->approval_mail = $dataEmp->email;
                    $model->approval_title = $dataEmp->title;
                    if($model->save()){
                        if($data_esk->status == "processed" && $sequence == $flag_app_seq){
                            //change new status
							$dataDecree 			= EskDecreeBy::findOne(['decree_by' => $emp]);
							$data_esk->number_esk 	= substr($data_esk->number_esk, 0,12) . substr($dataDecree->number_esk, 8) . substr($data_esk->number_esk, 19,25);
                            $data_esk->no_esk		= substr($data_esk->no_esk, 0,8) . substr($dataDecree->number_esk, 8);
							$data_esk->tracking 	= "Awaiting approval of ".$dataEmp->title." (".$dataEmp->nama.")";
                            $data_esk->save();
                        }
                        
                        //logging data
                        Model::saveLog(Yii::$app->user->identity->username, "Reassign approval eSK of user data with ID User ".$model->id);

                        //set flash berhasil reassign
                        Yii::$app->session->setFlash('success', "eSK Approval of user data successfully reassign!");
                    }else{
                        //logging data
                        $error = implode(",",$model->getErrorSummary(true));
                        Model::saveLog(Yii::$app->user->identity->username, "Failed approve eSK of user data for ID User ".$model->id." because ".$error);
                        
                        //set flassh failed reassign
                        Yii::$app->session->setFlash('error', "Failed reassign approval of eSK, because ".$error);
                    }
                }else{
                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Failed reassign approval of eSK data because employee data not found!");
                    Yii::$app->session->setFlash('error', "Failed reassign approval of eSK, employee data not found.");
                }
            }else{
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Failed reassign approval of eSK data because employee is same!");
                Yii::$app->session->setFlash('error', "Failed reassign eSK approval, employee data is same with approval.");
            }
    
            //balik ke detail
            if(empty($flag_staffing)){
                return $this->redirect(['detail', 'id'=>$model->id_esk]);
            }else{
                return $this->redirect(['staffing-lists/detail', 'id'=>$model->id_esk]);
            }
        }

        return $this->renderAjax('reassignapp', [
            'model' => $model
        ]);
    }

    public function actionDeleteApp($id){
        //get app data
        $model = EskApprovalLists::findOne($id);
        $id_esk = $model->id_esk;
        $sequence = $model->sequence;

        //get data esk lists
        $data_esk = EskLists::findOne($id_esk);
        $flag_app_seq = $data_esk->flag_approval_seq;

        if ($model->delete()) {
            if(($data_esk->status == "processed" || $data_esk->status == "generated") && $sequence == $flag_app_seq){
                //change new status
                $data_esk->flag_approval_seq = $model->next->sequence;
                if($data_esk->status == "processed"){
                    $data_esk->tracking = "Awaiting approval of ".$model->next->approval_title." (".$model->next->approval_name.")";
                }
                $data_esk->save();
            }

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Remove Approval of eSK data with ID Ack ".$model->id);

            //set flash berhasil reassign
            Yii::$app->session->setFlash('success', "Approval of of eSK data successfully removed!");
        }else{
            //logging data
            $error = implode(",",$model->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed remove approval of eSK data for ID User ".$model->id." because ".$error);
            
            //set flassh failed reassign
            Yii::$app->session->setFlash('error', "Failed removed approval of eSK data, because ".$error);
        }

        //balik ke detail
        return $this->redirect(['detail', 'id'=>$id_esk]);
    }

    public function acknowledge($id,$id_ack){
        //get data acknowledge 
        $ack = EskAcknowledgeLists::find()->where(['id_esk' => $id])->one();
        $countPending = EskAcknowledgeLists::find()->where(['id_esk' => $id])->andWhere(['status' => 'pending'])->count();

        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("delivered", $ack->id, $id);
        $data_esk = $this->findModel($id);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $data_esk->flag_ack_seq = $data_update['flag_approval_seq'];
        
        if($data_esk->save()){
            //update data approval statusnya
            $data_ack = EskAcknowledgeLists::findOne($id_ack);
			if(!empty($data_ack))
			{
				$data_ack->status = "acknowledge";
				$data_ack->ack_at = date("Y-m-d H:i:s");
				$data_ack->save();
				
				//save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
				$action = $data_ack->ack_title." mengakui eSK Karyawan.";
				Model::setWorkFlow($data_ack->id,$action,"cronack");

				//logging data
				Model::saveLog(Yii::$app->user->identity->username, "Acknowledge eSK data with ID ".$data_esk->id." by ".$data_ack->ack_title);

				//send mail ack
				$subject = "[eSK] Delivered of eSK Number ".$data_esk->number_esk."";
				$data_next_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
				$content = $this->renderPartial('../../mail/mail-delivered',['esk' => $data_esk, 'head' => $data_next_ack->ack_name],true);        
				Model::sendMailOne($data_next_ack->ack_mail,$subject,$content);

				//send mail to prev ack
				$subject = "[eSK] Delivered of eSK Number ".$data_esk->number_esk."";
				$content = $this->renderPartial('../../mail/mail-delivered-cron',['esk' => $data_esk, 'head' => $data_ack->ack_name],true);        
				Model::sendMailOne($data_ack->ack_mail,$subject,$content);
			}
        }
    }

    public function published($id_esk,$id_ack){
        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("published", '', $id_esk);
        $data_esk = $this->findModel($id_esk);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        
        if($data_esk->save()){
            //update ack jika tidak kosong
            if(!empty($id_ack)){
                //update data approval statusnya
                $data_ack = EskAcknowledgeLists::findOne($id_ack);
                $data_ack->status = "acknowledge";
                $data_ack->ack_at = date("Y-m-d H:i:s");
                $data_ack->save();
            }

            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            $action = $data_esk->vP->title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
            Model::setWorkFlow($id_esk,$action,"cronpublish");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$data_esk->vP->title);

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
            $subject = "[eSK] FYI Published of eSK Number ".$data_esk->number_esk."";
            $content = $this->renderPartial('../../mail/mail-published-ack',['data_esk' => $data_esk, 'nama_pengirim' => Yii::$app->user->identity->employee->nama],true);
            Model::sendMailMultiple($to,$subject,$content);
        }
    }

    public function actionPublished($id){
		set_time_limit(0);
        ini_set('memory_limit', '4048M');
		
        //kirim datanya ke fungsi workflow status
        $data_update = Model::WorkFlowStatus("published", '', $id);
        $data_esk = $this->findModel($id);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];

        if($data_esk->save()){
            //update all ack
            $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $id,'status' => 'pending'])->all();
            foreach ($data_ack as $data_ack) {
                $data_ack->status = "acknowledge";
                $data_ack->ack_at = date("Y-m-d H:i:s");
                $data_ack->update(false);
            }
            
            //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
            if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                $nik = Yii::$app->user->identity->nik;
            }else{
                 $nik = "";
            }

            $data_ack2 = EskAcknowledgeLists::find()->where(['id_esk' => $id,'sequence' => $data_esk->flag_ack_seq])->one();
            if($data_ack2->ack_nik == $nik){
                $action = $data_ack2->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
            }else{
                $action = $data_ack2->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.". (action by HCBP Account/Area)";
            }
            Model::setWorkFlow($data_esk->id,$action,"-");

            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$data_ack2->ack_title);

            //submit posting career 
            Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

            $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
			$to = $data_esk->employee->email;
			$content = $this->renderPartial('../../mail/mail-published',['data_esk' => $data_esk],true);
            Model::sendNotifMoana($to,'My Assignment • New Update',ucwords(strtolower($data_esk->about_esk)));
			Model::sendMailOne($to,$subject,$content);

            //set flash message
            Yii::$app->session->setFlash('success', "eSK data successfully published!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed published eSK data for ID ".$data_esk->id." because ".$error);
            Yii::$app->session->setFlash('error', "Failed published eSK, because ".$error);
        }

        return $this->redirect(['index']);
    }

    public function actionPublishedAll(){
		set_time_limit(0);
        ini_set('memory_limit', '4048M');
		
        $id_esk_data = yii::$app->request->get('id_esk');
		$id_esk = explode(",",$id_esk_data);
		
		//inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();
        
        foreach($id_esk as $id){
			//kirim datanya ke fungsi workflow status
            $data_update = Model::WorkFlowStatus("published", '', $id);
            $data_esk = $this->findModel($id);
            $data_esk->status = $data_update['status'];
            $data_esk->tracking = $data_update['tracking'];

            if($data_esk->save()){
                //update all ack
                $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $id,'status' => 'pending'])->all();
                foreach ($data_ack as $data_ack) {
                    $data_ack->status = "acknowledge";
                    $data_ack->ack_at = date("Y-m-d H:i:s");
                    $data_ack->update(false);
                }
                
                //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $nik = Yii::$app->user->identity->nik;
                }else{
                     $nik = "";
                }
    
                $data_ack2 = EskAcknowledgeLists::find()->where(['id_esk' => $id,'sequence' => $data_esk->flag_ack_seq])->one();
                if($data_ack2->ack_nik == $nik){
                    $action = $data_ack2->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.".";
                }else{
                    $action = $data_ack2->ack_title." menerbitkan eSK untuk ".$data_esk->nik."/".$data_esk->nama.". (action by HCBP Account/Area)";
                }
                Model::setWorkFlow($data_esk->id,$action,"-");
    
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID ".$data_esk->id." by ".$data_ack2->ack_title);
                
                //submit posting career 
                Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

                $subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
                //$subject = "Penyesuaian - Salinan Surat Keputusan (SK) Pengangkatan Karyawan Tetap Telkomsel";
				$to = $data_esk->employee->email;
                $content = $this->renderPartial('../../mail/mail-published',['data_esk' => $data_esk],true);
				$all_content = Model::setEskDataEmail($data_esk->id,$data_esk->about_esk,$data_esk->number_esk,$data_esk->content_esk,$data_esk->city_esk,$data_esk->decree_nama,$data_esk->decree_nik,$data_esk->decree_title,$data_esk->is_represented,$data_esk->represented_title,$data_esk->approved_esk_date,$data_esk->file_name,"preview");
				//$content = $this->renderPartial('../../mail/mail-published-fmc',['data_esk' => $data_esk, 'all_content' => $all_content],true);
                Model::sendNotifMoana($to,'My Assignment • New Update',ucwords(strtolower($data_esk->about_esk)));
                Model::sendMailOne($to,$subject,$content);
				
				//send mail to atasan langsung
				$subject = "[eSK] Published of eSK Number ".$data_esk->number_esk."";
				//$to = $data_esk->employee->email;
				$datakaryawan = Employee::findOne(['nik' => $data_esk->nik]);
				$atasan		  = Employee::findOne(['nik' => $datakaryawan->nik_atasan]);
				$to 		  = $atasan->email;
				//$to 		  = $data_esk->employee->email;
				//var_dump($to,$totes);exit;
				$content = $this->renderPartial('../../mail/mail-published-atasan-new',['data_esk' => $data_esk,'atasan' => $atasan],true);
				Model::sendMailOne($to,$subject,$content);
					
                //set success count
                $countSuccess = $countSuccess + 1;
            }else{
                //set failed count
                $countFailed = $countFailed + 1;

                //logging data
                $error = implode(",",$data_esk->getErrorSummary(true));
                Model::saveLog(Yii::$app->user->identity->username, "Failed publish eSK data for ID ".$data_esk->id." because ".$error);
                array_push($failed_array,"data eSK ".$data_esk->nik."/".$data_esk->nama."/".$data_esk->tipe." failed publish because ".$error);
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
		
		if(Yii::$app->user->can('sysadmin')) {
			return $this->redirect(['index']);	
		} else {
			return $this->redirect(['staffing-lists/index']);
		}
    }

    public function actionModalPublish(){
        $id = yii::$app->request->get('id');
		
        return $this->renderAjax('publishdialog',[
            "id" => $id,
        ]);
    }

    public function actionModalRegenerate(){
        $id = yii::$app->request->get('id');

        return $this->renderAjax('regeneratedialog',[
            "id" => $id,
        ]);
    }

    public function actionDeleteEsk($id,$reason){
        $data_update = Model::WorkFlowStatus("waiting_cancel", '', $id);
        $data_esk = $this->findModel($id);
        $data_esk->status = $data_update['status'];
        $data_esk->tracking = $data_update['tracking'];
        $data_esk->reason_cancel = $reason;

        if ($data_esk->save()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Canceled of eSK data with ID ".$data_esk->id);

            //send notification to VP HC Business Partner
            $data_ack = EskAcknowledgeSettings::find()->where(['category' => '12'])->one();
            if(!empty($data_ack)){
                $subject = "[eSK] Cancel of eSK Number ".$data_esk->number_esk."";
                $to = $data_ack->employee->email;
                $content = $this->renderPartial('../../mail/mail-cancel',['head' => $data_ack->employee->nama, 'esk' => $data_esk, 'reason' => $reason],true);
                Model::sendMailOne($to,$subject,$content);
            }

            //set flash berhasil reassign
            Yii::$app->session->setFlash('success', "eSK data successfully canceled and waiting for confirmation!");
        }else{
            //logging data
            $error = implode(",",$data_esk->getErrorSummary(true));
            Model::saveLog(Yii::$app->user->identity->username, "Failed canceled of eSK data for ID ".$data_esk->id." because ".$error);
            
            //set flassh failed reassign
            Yii::$app->session->setFlash('error', "Failed canceled of eSK data, because ".$error);
        }

        //balik ke detail
        return $this->redirect(['index']);
    }
	
	public function actionDeleteEskNew($id){
        
		$data_update 		= Model::WorkFlowStatus("waiting_cancel", '', $id);
        $model 				= $this->findModel($id);
        

		if ($model->load(Yii::$app->request->post())) {
			
			$model->status 		= $data_update['status'];
			$model->tracking 	= $data_update['tracking'];
		
			if($model->save()){
				//logging data
				Model::saveLog(Yii::$app->user->identity->username, "Canceled of eSK data with ID ".$model->id);

				//send notification to VP HC Business Partner
				$data_ack = EskAcknowledgeSettings::find()->where(['category' => '12'])->one();
				if(!empty($data_ack)){
					$subject = "[eSK] Cancel of eSK Number ".$model->number_esk."";
					$to = $data_ack->employee->email;
					$content = $this->renderPartial('../../mail/mail-cancel',['head' => $data_ack->employee->nama, 'esk' => $model, 'reason' => ''],true);
					Model::sendMailOne($to,$subject,$content);
				}
				
				if($model->file_cancel = UploadedFile::getInstance($model, 'file_cancel'))
				{
					$model->file_cancel = UploadedFile::getInstance($model, 'file_cancel');
					$filename = 'file_cancel' . $model->id . '.' . $model->file_cancel->extension;
					$model->file_cancel->saveAs('file_batal/' . $filename);
					$model->file_cancel = null;
				}
				
				$updateModel = EskLists::find()->where(['id' => $model->id])->one();
				$updateModel->nama_dokumen_cancel = $filename;
				$updateModel->save();
				
				//set flash berhasil reassign
				Yii::$app->session->setFlash('success', "eSK data successfully canceled and waiting for confirmation!");
			}else{
				//logging data
				$error = implode(",",$model->getErrorSummary(true));
				Model::saveLog(Yii::$app->user->identity->username, "Failed canceled of eSK data for ID ".$model->id." because ".$error);
				
				//set flassh failed reassign
				Yii::$app->session->setFlash('error', "Failed canceled of eSK data, because ".$error);
			}
			
			return $this->redirect(array('esk-lists/index'));
			
		} else {
			return $this->renderAjax('_form_cancel', [
				'model' => $model,
			]);
		}			

        
    }
	
	public function actionUnduh($namafile) 
	{ 
		$path = Yii::getAlias('@webroot').'/file_batal/' . $namafile;

		if (file_exists($path)) {
			return Yii::$app->response->sendFile($path);
		}
		
		exit;
	}
	
    public function actionReport(){
        $week_before = date("Y-m-d",strtotime('-1 weeks'));
        $today = date("Y-m-d",strtotime('1 days'));
        
        //get data and set data
        $data = EskLists::find()
        ->select('nik, nama, old_position, new_position, old_bp, new_bp, old_bi, new_bi, old_kota, new_kota, effective_esk_date, tipe, tracking','about_esk')
        ->where('created_at BETWEEN "'.$week_before.'" AND "'.$today.'"')
        ->orderBy('effective_esk_date ASC')
        ->all();

        if(!empty($data)){
            $implode_data = array();
            $i = 1;
            foreach($data as $esk){
                array_push($implode_data,"
                <tr>
                    <td width='3%' align='center'>".$i."</td>
                    <td width='7%' align='center'>".$esk->nik."</td>
                    <td width='10%' align='left'>".$esk->nama."</td>
                    <td width='11%' align='left'>".$esk->tipe."</td>
                    <td width='12%' align='left'>".$esk->about_esk."</td>
                </tr>
                ");
                $i++;
            }
			/*
			 <tr>
                    <td width='3%' align='center'>".$i."</td>
                    <td width='7%' align='center'>".$esk->nik."</td>
                    <td width='10%' align='left'>".$esk->nama."</td>
                    <td width='10%' align='left'>".$esk->old_position."</td>
                    <td width='10%' align='left'>".$esk->new_position."</td>
                    <td width='5%' align='center'>".$esk->old_bp."</td>
                    <td width='5%' align='center'>".$esk->new_bp."</td>
                    <td width='5%' align='center'>".$esk->old_bi."</td>
                    <td width='5%' align='center'>".$esk->new_bi."</td>
                    <td width='8%' align='left'>".$esk->old_kota."</td>
                    <td width='8%' align='left'>".$esk->new_kota."</td>
                    <td width='8%' align='center'>".Model::TanggalIndo($esk->effective_esk_date)."</td>
                    <td width='11%' align='left'>".$esk->tipe."</td>
                    <td width='12%' align='left'>".$esk->tracking."</td>
                </tr>
			*/
            $gm_hcoa = EskAcknowledgeSettings::find()->where(['category' => '9'])->one();
            if(!empty($gm_hcoa)){
                $gm_data = $gm_hcoa->employee;
            }else{
                $gm_data = Employee::find()->where(['position_id' => '122113'])->one();
            }

            if($gm_data){
                //send mail to $gm_hcoa
                $subject = "[eSK] Information of Processing eSK";
                $to = $gm_data->email;
                $content = $this->renderPartial('@esk/mail/mail-information-week',['head' => $gm_data->nama, 'esk' => $implode_data, 'week_before' => $week_before, 'today' => date('Y-m-d')],true);
                Model::sendMailOne($to,$subject,$content);
                echo "Send email to General Manager HC Operation and Administration";
            }
        }
    }
	
	public function actionTest()
	{
		var_dump(Yii::$app->user->identity->employee->nama);exit;
	}
	
		public function actionEmployeelist($q = null, $id = 0) {
		Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		
		$out = ['results' => ['id' => '', 'text' => '']];
		
		if (!is_null($q)) {		
			$data = Employee::find()->where(['status' => 'AKTIF'])->andWhere(['like', 'nama', $q])->limit(100)->all();
			
			foreach($data as $rows)
				$tmp[] = ["id" => $rows->nik, "text" => "(". $rows->nik .") " .$rows->nama];
			
			$out['results'] = empty($tmp) ? ['id' => '', 'text' => ''] : $tmp;
		}elseif ($id > 0) {
			$out['results'] = ['id' => $id, 'text' => Employee::findOne(['nik' => $id])->nama];
		}
		
		return $out;
	}
	
    protected function findModel($id)
    {
        if (($model = EskLists::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

     /**
     * Lists all esk with status published for sync to ebs.
     * @return mixed
     */
    public function actionSyncEsk($data_error = null)
    {   
		set_time_limit(0);
		ini_set('memory_limit', '3048M');
		
		$flag = yii::$app->request->get('flag');
		
		$isexport = false;
		if($flag == "export")
			$isexport = true;
		
        if(
            (!empty(yii::$app->request->get('start_date')) && !empty(yii::$app->request->get('end_date'))) 
            || !empty(yii::$app->request->get('tipe')) || !empty(yii::$app->request->get('id')) 
            || !empty(yii::$app->request->get('new_position')) || !empty(yii::$app->request->get('nik'))
            || !empty(yii::$app->request->get('nama'))
            || !empty(yii::$app->request->get('group'))
            || !empty(yii::$app->request->get('reason'))
        ){
            //filter
            $start_date = date("Y-m-d",strtotime(yii::$app->request->get('start_date')));
            $end_date = date("Y-m-d",strtotime(yii::$app->request->get('end_date')));
            $start_date_data = yii::$app->request->get('start_date');
            $end_date_data = yii::$app->request->get('end_date');
            $tipe = yii::$app->request->get('tipe');
            $id = yii::$app->request->get('id');
            $newpos = yii::$app->request->get('new_position');
            $nik = yii::$app->request->get('nik');
            $nama = yii::$app->request->get('nama');
            // add by faqih
            $group = yii::$app->request->get('group');
            $reason = yii::$app->request->get('reason');
            // end
        
            $queryDate = (empty($start_date_data) && empty($end_date_data)) ? '' : "and (effective_esk_date between '".$start_date."' and '".$end_date."')";
            $queryTipe = (empty($tipe)) ? '' : "and tipe like '%".$tipe."%'";
            $queryID = (empty($id)) ? '' : "and id = '".$id."'";
            $queryPosition = (empty($newpos)) ? '' : "and new_position like '%".$newpos."%'";
            $queryNik = (empty($nik)) ? '' : "and nik like '%".$nik."%'";
            $queryNama = (empty($nama)) ? '' : "and nama like '%".$nama."%'";
            // add by faqih
            $queryGroup = (empty($group)) ? '' : "and groups_reason like '%".$group."%'";
            $queryReason = (empty($reason)) ? '' : "and reason like '%".$reason."%'";
            // end

            if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                // die("ini");
                $query_where = "nik <> '' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryPosition." ".$queryNik." ".$queryNama." ".$queryGroup." ".$queryReason;

                $sql = "SELECT * FROM esklist_group_reason_v WHERE ".$query_where." and (status = 'published' AND sync_status = 0 AND id_reason is not NULL) ORDER BY created_at DESC";

                if($isexport)
                    // $dataExport = EskLists::find()->where($query_where)->andWhere('status = "published" AND sync_status = 0')->orderBy('created_at ASC')->all(); 

                    
                    $dataExport = Yii::$app->db->createCommand($sql)->queryAll();

            }else{
                // die("itu");
                //get area user
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                } else{
                    $user_area = "N/A";
                }
                
                $query_where = "nik <> '' and authority = '".$user_area."' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryPosition." ".$queryNik." ".$queryNama." ".$queryGroup." ".$queryReason;

                $sql = 'SELECT * FROM esklist_group_reason_v WHERE '.$query_where.' and (status = "published" AND sync_status = 0 AND id_reason is not NULL)  ORDER BY created_at DESC';

                if($isexport)
                    // $dataExport = EskLists::find()->where($query_where)->andWhere('status = "published" AND sync_status = 0')->orderBy('created_at ASC')->all();

                    
                    $dataExport = Yii::$app->db->createCommand($sql)->queryAll();
            }
        }else{
            // die("ini2");
            $query_where = "id IS NOT NULL";
            $sql = 'SELECT * FROM esklist_group_reason_v WHERE '.$query_where.' and (status = "published" AND sync_status = 0 ) ORDER BY created_at DESC';
            if($isexport)
					// $dataExport = EskLists::find()->where('status = "published" AND sync_status = 0')->orderBy('created_at ASC')->all(); 

                    
                    $dataExport = Yii::$app->db->createCommand($sql)->queryAll();
        }

        //encrypt query
        $query_search = Yii::$app->security->encryptByKey($query_where, 'esk.enhancement.2021');

        //fungsi export data 
        if(!empty(yii::$app->request->get('flag')) && yii::$app->request->get('flag') == "export"){
            if(!empty($dataExport)){
                $this->exportData($dataExport, 'E-SK Synchronize Data');
            }else{
                Yii::$app->session->setFlash('error', "Failed export, data is empty!");
            }
        }

        return $this->render('sync_esk', [
            'start_date' => $start_date_data,
            'end_date' => $end_date_data,
            'tipe' => $tipe,
            'id_esk' => $id,
            'new_position' => $newpos,
            'nik' => $nik,
            'nama' => $nama,
            'group' => $group,
            'reason' => $reason,
            'query_search' => $query_search,
            'data_error' => $data_error
        ]);
    }

    public function actionSyncEskDataOld($query_search){
        set_time_limit(0);
		ini_set('memory_limit', '3048M');
        
        //decrypt 
        $query_where = Yii::$app->security->decryptByKey($query_search, 'esk.enhancement.2021');

        $data = EskLists::find()->where('status = "published" AND sync_status = 0')->andWhere($query_where)->orderBy('created_at ASC')->all();
        $totalData = 0;
        $model = [];
        foreach($data as $row){
            $action =  Html::a('<span class="glyphicon glyphicon-eye-open" style="color:blue;"></span>', ['detail','id'=>$row->id,'flag' => 'sync'], ['title' => 'View Detail']).'&nbsp;&nbsp;&nbsp;'.
            Html::a('<span class="fa fa-file-pdf-o" style="color:brown;"></span>', ['preview','id'=>$row->id,'flag' => 'sync'], ['title' => 'preview']).'&nbsp;&nbsp;&nbsp;'; 

            //validate flag backdate
            $tgl_esk = date("Y-m-d",strtotime($row->effective_esk_date));
            $today = date("Y-m-d");
            $selisih = abs(strtotime($today) - strtotime($tgl_esk));
            $days = floor($selisih / (60*60*24));
            if ($days <= 2) {
                $color_flag = "green";
            } elseif ($days > 2 && $days <= 5) {
                $color_flag = "yellow";
            } else {
                $color_flag = "red";
            }
            $flag_backdate = (strtotime($today) > strtotime($tgl_esk)) ? " <i class='fa fa-tag' style='color:".$color_flag."' title='backdate'></i>" : "";  
            $nestedData = array();
            $nestedData[] = $row->id;
            $nestedData[] = $row->nik;
            $nestedData[] = empty($row->employee) ? "-" : $row->employee->nama;
            $nestedData[] = $row->about_esk;
            $nestedData[] = $row->number_esk;
            $nestedData[] = $row->tracking;
            $nestedData[] = empty($row->effective_esk_date) ? "-" : date("d-M-Y",strtotime($row->effective_esk_date)).$flag_backdate;
            $nestedData[] = $row->sync_result;
            $nestedData[] = $action;
            $model[]      = $nestedData;
            $totalData++;
        }

        $json_data = array(
            "draw" => $model, 
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalData),
            "data" => $model
        );
    
        return \yii\helpers\Json::encode($json_data);
    }

    public function actionSyncEskData($query_search){
        set_time_limit(0);
		ini_set('memory_limit', '3048M');
        
        $requestData = Yii::$app->request->get();
        $start = Yii::$app->request->get('start')==null ? 1 : Yii::$app->request->get('start');
        $length = Yii::$app->request->get('length')==null ? 10 : Yii::$app->request->get('length');
        $draw = Yii::$app->request->get('draw');

        //decrypt 
        $query_where = Yii::$app->security->decryptByKey($query_search, 'esk.enhancement.2021');

        // $sql = 'SELECT * FROM esk_lists WHERE '.$query_where.' and (status = "published" AND sync_status = 0)';

        // add by faqih validation effective esk date 19/04/2024
        if(date('Y-m-d') < date('Y-m-').'11'){
            $querydate = "and DATE_FORMAT(effective_esk_date,'%Y-%m') >=  DATE_FORMAT(CURRENT_DATE,'%Y-%m')";
        }else{
            $querydate = "and DATE_FORMAT(effective_esk_date,'%Y-%m') >  DATE_FORMAT(CURRENT_DATE,'%Y-%m')";
        }

        $sql = "SELECT * FROM esklist_group_reason_v WHERE ".$query_where." and (status = 'published' AND sync_status = 0 AND id_reason is not NULL) ".$querydate."";

        $data = Yii::$app->db->createCommand($sql)->queryAll();
        //$data = EskLists::find()->where('status = "published" AND sync_status = 0')->andWhere($query_where)->orderBy('created_at ASC')->all();
        $totalData = count($data);
        $totalFiltered = $totalData;

        if (!empty($requestData['search']['value'])){
            $sql.=" AND ( nik LIKE '%" . $requestData['search']['value'] . "%' ";
            $sql.=" OR nama LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR about_esk LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR number_esk LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR effective_esk_date LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR status LIKE '%" . $requestData['search']['value'] . "%'";
            // add by faqih
            $sql.=" OR groups_reason LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR reason LIKE '%" . $requestData['search']['value'] . "%')";
            // end
        }
        $data = Yii::$app->db->createCommand($sql)->queryAll();
        $totalFiltered = count($data);

        $sql.=" ORDER BY created_at DESC LIMIT ".$start." ,".$length." ";
        $result = Yii::$app->db->createCommand($sql)->queryAll();

        $data = array();
        foreach($result as $key => $row){
            $action =  Html::a('<span class="glyphicon glyphicon-eye-open" style="color:blue;"></span>', ['detail','id'=>$row['id'],'flag' => 'sync'], ['title' => 'View Detail']).'&nbsp;&nbsp;&nbsp;'.
            Html::a('<span class="fa fa-file-pdf-o" style="color:brown;"></span>', ['preview','id'=>$row['id'],'flag' => 'sync'], ['title' => 'preview']).'&nbsp;&nbsp;&nbsp;'; 

            //validate flag backdate
            $tgl_esk = date("Y-m-d",strtotime($row['effective_esk_date']));
            $today = date("Y-m-d");
            $selisih = abs(strtotime($today) - strtotime($tgl_esk));
            $days = floor($selisih / (60*60*24));
            if ($days <= 2) {
                $color_flag = "green";
            } elseif ($days > 2 && $days <= 5) {
                $color_flag = "yellow";
            } else {
                $color_flag = "red";
            }

            $flag_backdate = (strtotime($today) > strtotime($tgl_esk)) ? " <i class='fa fa-tag' style='color:".$color_flag."' title='backdate'></i>" : "";  
            $nestedData = array();
            $nestedData[] = $row['id'];
            $nestedData[] = $row['nik'];
            $nestedData[] = $row["nama"];
            $nestedData[] = $row['about_esk'];
            $nestedData[] = $row['number_esk'];
            $nestedData[] = $row['groups_reason'];
            $nestedData[] = $row['reason'];
            $nestedData[] = $row['tracking'];
            $nestedData[] = empty($row['effective_esk_date']) ? "-" : date("d-M-Y",strtotime($row['effective_esk_date'])).$flag_backdate;
            $nestedData[] = $row['sync_result'];
            $nestedData[] = $action;
            $data[]      = $nestedData;
        }

         $json_data = array(
            "draw" => $draw, 
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data
        );
    
        return \yii\helpers\Json::encode($json_data);
    }

    public function actionModalSynchronize(){
        $flag = yii::$app->request->get('flag');
        $id = yii::$app->request->get('id');
		
        return $this->renderAjax('synchronizedialog',[
            "id" => $id,
            "flag" => $flag,
        ]);
    }

    public function actionSynchronizeAll(){
        set_time_limit(0);
        ini_set('memory_limit', '9048M');
		
        $id_esk_data = yii::$app->request->get('id_esk');
        $flag = yii::$app->request->get('flag_esk');
		$id_esk = explode(",",$id_esk_data);

        //inisialisasi data count 
        $countSuccess = 0;
        $countFailed = 0;
        $countAll = 0;
        $failed_array = array();
        $success_array = array();

        foreach($id_esk as $id_esk){
            //get esk data based on id
            $model = EskLists::findOne($id_esk);

            $sqllist = 'SELECT * FROM esklist_group_reason_v WHERE id = '.$id_esk.'';
            $data_esk = Yii::$app->db->createCommand($sqllist)->queryOne();

            //validasi tipe
            /*if($data_esk->tipe == "Pengangkatan"){
                //search ba data
                $ba_data = EskBeritaAcaraDetailOther::findOne($data_esk->id_ba_detail);
            }else{
                $ba_data = [];
            }*/

            //sync to ebs process update by faqih
            // sprint 3

            // faqih : add param p_new_position_id 08/07/2024 - 05/07/2024
            $sql = "
            declare
                v_message       VARCHAR2(2000);
                v_result         VARCHAR2(200);
            begin
                TSEL_HC_ESK.process_esk (
                    p_effective_esk_date        => to_date(:effective_esk_date,'YYYY-MM-DD'),
                    p_esk_id                    => :esk_id,
                    p_no_esk                    => :esk_number,
                    p_nik                       => :nik,
                    p_new_nik					=> :nik_baru,
                    p_tipe                      => :tipe,
                    p_about_esk                 => :about_esk,
                    p_group                     => :group,
                    p_reason                    => :reason,
                    p_new_position_id	 		=> :new_position_id,
                    p_new_position              => :new_position,
                    p_new_organization          => :new_organization,
                    p_new_grade                 => :new_grade,
                    p_nik_supervisor            => :nik_new_atasan,
                    p_new_title                 => :new_title,
                    p_new_bp                    => :new_bp,
                    p_lvl_gaji                  => :level_gaji,  
                    p_period                    => :period,
                    p_dpe_length                => :dpe_length,
                    p_dpe_unit                  => :dpe_unit,
                    p_gaji_dasar_nss            => to_number(:gaji_dasar_nss),
                    p_tunj_hotskill             => to_number(:tunjangan_hot_skill),
                    p_tunj_aktualisasi          => to_number(:tunjangan_aktualisasi),
                    p_login_person_nik          => :login_nik,
                    P_leaving_reason			=> :leaving_reason,
                    p_statement_date			=> to_date(:notif_stat_date,'YYYY-MM-DD'),
                    p_message                   => :message,
                    p_result                    => :result
                );

                dbms_output.put_line( 'v_message ' || v_message);
                dbms_output.put_line( 'v_result ' || v_result);
            end;
            ";

            $message = "";
            $result = "";

            //check position 
            $filterPosition = str_replace("Pj. ","",$data_esk['new_position']);
            $new_position_data = Position::find()->where(['nama' => trim($filterPosition)])->andWhere("(position_code iS NOT NULL or position_code <> '') and status = 1 and id = ".$data_esk['new_position_id']."")->one();

            if(!empty($new_position_data)){
                $conn = Yii::$app->dbOra;
                $command = $conn->createCommand(trim(preg_replace('/\s\s+/', ' ', $sql)))
                ->bindValue(":effective_esk_date", $data_esk['effective_esk_date'])
                ->bindValue(":esk_id", $data_esk['id'])
                ->bindValue(":esk_number", $data_esk['number_esk'])
                ->bindValue(":nik", $data_esk['nik'])
                ->bindValue(":nik_baru", $data_esk['new_nik']) // sprint 3 
                ->bindValue(":tipe", $data_esk['tipe'])
                ->bindValue(":about_esk", $data_esk['about_esk'])
                ->bindValue(":group", $data_esk['groups_reason'])
                ->bindValue(":reason", $data_esk['reason'])
                ->bindValue(":new_position_id", $data_esk['new_position_id'])
                ->bindValue(":new_position", $new_position_data['position_code'].".".$new_position_data['nama'])
                ->bindValue(":new_organization", $data_esk['new_organization'])
                ->bindValue(":new_grade", $data_esk['grade'])
                ->bindValue(":nik_new_atasan", $data_esk['nik_new_atasan'])
                ->bindValue(":new_title", $data_esk['new_title'])
                ->bindValue(":new_bp", $data_esk['level_band'])
                ->bindValue(":level_gaji", $data_esk['level_gaji'])
                ->bindValue(":period", $data_esk['periode'])
                ->bindValue(":dpe_length", $data_esk['dpe_length'])
                ->bindValue(":dpe_unit", $data_esk['dpe_unit'])
                ->bindValue(":gaji_dasar_nss", empty($data_esk['gaji_dasar_nss']) ? 0 : $data_esk['gaji_dasar_nss'])
                ->bindValue(":tunjangan_hot_skill", empty($data_esk['tunjangan_hot_skill']) ? 0 : $data_esk['tunjangan_hot_skill'])
                ->bindValue(":tunjangan_aktualisasi", empty($data_esk['tunjangan_aktualisasi']) ? 0 : $data_esk['tunjangan_aktualisasi']) // sprint 3
                ->bindValue(":login_nik", empty(Yii::$app->user->identity->nik) ? 0 : Yii::$app->user->identity->nik)
                ->bindValue(":leaving_reason", $data_esk['leaving_reason']) // sprint 3
                ->bindValue(":notif_stat_date", $data_esk['notif_stat_date']) // sprint 3
                ->bindParam(":message", $message,PDO::PARAM_STR,225)
                ->bindParam(":result", $result,PDO::PARAM_STR,50)
                ->execute();
    
                if(!empty($result) && $result == "SUCCESS"){
                    //set success count
                    $countSuccess = $countSuccess + 1;
    
                    //logging data
                    $warning_label = empty($message) ? "" : " with note ".$message;
                    Model::saveLog(Yii::$app->user->identity->username, "HC Staffing sync eSK data with ID ".$data_esk['id'].$warning_label );
                    
                    array_push($success_array,"data eSK ".$data_esk['nik']."/".$data_esk['nama']."/".$data_esk['tipe']." success sync eSK".$warning_label." \r\n");

                    //update eSK Data by faqih sprint 4
                    $model->sync_status = 1;
                    $model->sync_date   = date("Y-m-d H:i:s");
                    $model->flag_update   = null;
                    $model->sync_result   = null;
                }else{
                    $model->sync_result = "failed sync eSK because ".$message;
                    //set failed count
                    $countFailed = $countFailed + 1;
                    array_push($failed_array,"data eSK ".$data_esk['nik']."/".$data_esk['nama']."/".$data_esk['tipe']." failed sync eSK because ".$message."\r\n");
                    Model::saveLog(Yii::$app->user->identity->username, "Failed sync eSK data for ID ".$data_esk['id']." because ".$message);
                }
            }else{
                $model->sync_result = "failed sync eSK because detail new position data on HC Portal is not found";
                //set failed count
                $countFailed = $countFailed + 1;
                array_push($failed_array,"data eSK ".$data_esk['nik']."/".$data_esk['nama']."/".$data_esk['tipe']." failed sync eSK because detail new position data on HC Portal is not found\r\n");
                Model::saveLog(Yii::$app->user->identity->username, "Failed sync eSK data for ID ".$data_esk['id']." because detail new position data on HC Portal is not found");
            }
            
            $model->save();
            //count iteration
            $countAll = $countAll + 1;
        }

        //check failed
        if(!empty($success_array)){
            $success_data = implode("",array_unique($success_array));
        }else{
            $success_data = "";
        }

        if(!empty($failed_array)){
            $failed_data = implode("",array_unique($failed_array));
        }else{
            $failed_data = "";
        }

        $separator = "=======================================================================================================================================================\r\n";
        $log_data = empty($success_data) ? $failed_data : $success_data.$separator.$failed_data;

        //set flash message 
        Yii::$app->session->setFlash('info', 'Successfully sync ' . $countAll . ' eSK data with Success ' . $countSuccess . ' data and Fail ' . $countFailed . ' data.');

        if($flag == "sync"){
            return $this->redirect(['sync-esk','data_error' => $log_data ]);	
        }else{
            return $this->redirect(['synced-esk','data_error' => $log_data ]);	
        }
    }

    public function actionShowError($data){
        Model::exportError($data);
    }

     /**
     * Lists all esk with status published and already synched from ebs.
     * @return mixed
     */
    public function actionSyncedEsk($data_error = null)
    {   
		set_time_limit(0);
		ini_set('memory_limit', '3048M');
		
		$flag = yii::$app->request->get('flag');
		
		$isexport = false;
		if($flag == "export")
			$isexport = true;
		
        if(
            (!empty(yii::$app->request->get('start_date')) && !empty(yii::$app->request->get('end_date'))) 
            || !empty(yii::$app->request->get('tipe')) || !empty(yii::$app->request->get('id')) 
            || !empty(yii::$app->request->get('new_position')) || !empty(yii::$app->request->get('nik'))
            || !empty(yii::$app->request->get('nama')) || !empty(yii::$app->request->get('about_esk'))
            || !empty(yii::$app->request->get('sync_date'))
            || !empty(yii::$app->request->get('group'))
            || !empty(yii::$app->request->get('reason'))
        ){
            //filter
            $start_date = date("Y-m-d",strtotime(yii::$app->request->get('start_date')));
            $end_date = date("Y-m-d",strtotime(yii::$app->request->get('end_date')));
            $start_date_data = yii::$app->request->get('start_date');
            $end_date_data = yii::$app->request->get('end_date');
            $tipe = yii::$app->request->get('tipe');
            $id = yii::$app->request->get('id');
            $newpos = yii::$app->request->get('new_position');
            $nik = yii::$app->request->get('nik');
            $nama = yii::$app->request->get('nama');
            $about_esk = yii::$app->request->get('about_esk');
            $sync_date = yii::$app->request->get('sync_date');
            // add by faqih
            $group = yii::$app->request->get('group');
            $reason = yii::$app->request->get('reason');
            // end
        
            $queryDate = (empty($start_date_data) && empty($end_date_data)) ? '' : "and (effective_esk_date between '".$start_date."' and '".$end_date."')";
            $queryTipe = (empty($tipe)) ? '' : "and tipe like '%".$tipe."%'";
            $queryID = (empty($id)) ? '' : "and id = '".$id."'";
            $queryPosition = (empty($newpos)) ? '' : "and new_position like '%".$newpos."%'";
            $queryNik = (empty($nik)) ? '' : "and nik like '%".$nik."%'";
            $queryNama = (empty($nama)) ? '' : "and nama like '%".$nama."%'";
            $queryAbout = (empty($about_esk)) ? '' : "and about_esk like '%".$about_esk."%'";
            $querySync = (empty($sync_date)) ? '' : "and sync_date like '".$sync_date."-%'";
            // add by faqih
            $queryGroup = (empty($group)) ? '' : "and groups_reason like '%".$group."%'";
            $queryReason = (empty($reason)) ? '' : "and reason like '%".$reason."-%'";
            // end

            if(Yii::$app->user->can('sysadmin') || Yii::$app->user->can('hc_staffing')){
                $query_where = "nik <> '' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryPosition." ".$queryNik." ".$queryNama." ".$queryAbout." ".$querySync." ".$queryGroup." ".$queryReason;
                
                $sql = 'SELECT * FROM esklist_group_reason_v WHERE '.$query_where.' and (sync_status = 1 or flag_update =1) ORDER BY sync_date DESC';
                if($isexport)
                    // $dataExport = EskLists::find()->where($query_where)->andWhere('status = "published" AND sync_status = 1')->orderBy('sync_date DESC')->all(); 

                    
                    $dataExport = Yii::$app->db->createCommand($sql)->queryAll();
            }else{
                //get area user
                if(!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee)){
                    $user_area = Yii::$app->user->identity->employee->area;
                } else{
                    $user_area = "N/A";
                }
                
                $query_where = "nik <> '' and authority = '".$user_area."' ".$queryDate." ".$queryTipe." ".$queryID." ".$queryPosition." ".$queryNik." ".$queryNama." ".$queryAbout." ".$querySync." ".$queryGroup." ".$queryReason;
                $sql = 'SELECT * FROM esklist_group_reason_v WHERE '.$query_where.' and (sync_status = 1 or flag_update =1) ORDER BY sync_date DESC';
                if($isexport)
                    // $dataExport = EskLists::find()->where($query_where)->andWhere('status = "published" AND sync_status = 1')->orderBy('sync_date DESC')->all();

                    
                    $dataExport = Yii::$app->db->createCommand($sql)->queryAll();
            }
        }else{
            $query_where = "id IS NOT NULL";
            $sql = 'SELECT * FROM esklist_group_reason_v WHERE '.$query_where.' and (sync_status = 1 or flag_update =1) ORDER BY sync_date DESC';
            if($isexport)
					// $dataExport = EskLists::find()->where('status = "published" AND sync_status = 1')->orderBy('sync_date DESC')->all(); 

                    
                    $dataExport = Yii::$app->db->createCommand($sql)->queryAll();
        }

        //encrypt query
        $query_search = Yii::$app->security->hashData($query_where, 'esk.enhancement.2021');

        //fungsi export data 
        if(!empty(yii::$app->request->get('flag')) && yii::$app->request->get('flag') == "export"){
            if(!empty($dataExport)){
                $this->exportData($dataExport, 'E-SK Synched EBS Data');
            }else{
                Yii::$app->session->setFlash('error', "Failed export, data is empty!");
            }
        }

        return $this->render('synced_esk', [
            'start_date' => $start_date_data,
            'end_date' => $end_date_data,
            'tipe' => $tipe,
            'id_esk' => $id,
            'new_position' => $newpos,
            'nik' => $nik,
            'nama' => $nama,
            'about' => $about_esk,
            'sync_date' => $sync_date,
            'group' => $group,
            'reason' => $reason,
            'query_search' => $query_search,
            'data_error' => $data_error
        ]);
    }

    public function actionSyncedEskDataOld($query_search){
        set_time_limit(0);
		ini_set('memory_limit', '3048M');
        
        //decrypt 
        $query_where = Yii::$app->security->validateData($query_search, 'esk.enhancement.2021');

        $data = EskLists::find()->where($query_where)->andWhere('status = "published" AND sync_status = 1')->orderBy('sync_date DESC')->all();
        $totalData = 0;
        $model = [];
        foreach($data as $row){
            $action =  Html::a('<span class="fa fa-file-pdf-o" style="color:brown;"></span>', ['preview','id'=>$row->id,'flag' => 'synced'], ['title' => 'preview']); 
            
            //validate flag backdate
            $tgl_esk = date("Y-m-d",strtotime($row->effective_esk_date));
            $sync_date = empty($row->sync_date) ? date("Y-m-d") : date("Y-m-d",strtotime($row->sync_date));
            $selisih = abs(strtotime($sync_date) - strtotime($tgl_esk));
            $days = floor($selisih / (60*60*24));
            if ($days <= 2) {
                $color_flag = "green";
            } elseif ($days > 2 && $days <= 5) {
                $color_flag = "yellow";
            } else {
                $color_flag = "red";
            }
            $flag_backdate = (strtotime($sync_date) > strtotime($tgl_esk)) ? " <i class='fa fa-tag' style='color:".$color_flag."' title='backdate'></i>" : "";  

            $nestedData = array();
            $nestedData[] = $row->id;
            $nestedData[] = $row->nik;
            $nestedData[] = empty($row->employee) ? "-" : $row->employee->nama;
            $nestedData[] = $row->about_esk;
            $nestedData[] = $row->number_esk;
            $nestedData[] = empty($row->effective_esk_date) ? "-" : date("d-M-Y",strtotime($row->effective_esk_date)).$flag_backdate;
            $nestedData[] = empty($row->sync_date) ? "-" : date("d-M-Y",strtotime($row->sync_date));
            $nestedData[] = $action;
            $model[]      = $nestedData;
            $totalData++;
        }

        $json_data = array(
            "draw" => $model, 
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalData),
            "data" => $model
        );
    
        return \yii\helpers\Json::encode($json_data);
    }

    public function actionSyncedEskData($query_search){
        set_time_limit(0);
		ini_set('memory_limit', '8048M');

        $requestData = Yii::$app->request->get();
        $start = Yii::$app->request->get('start')==null ? 1 : Yii::$app->request->get('start');
        $length = Yii::$app->request->get('length')==null ? 10 : Yii::$app->request->get('length');
        $draw = Yii::$app->request->get('draw');

        //decrypt 
        $query_where = Yii::$app->security->validateData($query_search, 'esk.enhancement.2021');

        // $sql = 'SELECT * FROM esk_lists WHERE '.$query_where.' and (status = "published" AND sync_status = 1)';

        // add by faqih validation effective esk date 19/04/2024
        if(date('Y-m-d') < date('Y-m-').'11'){
            $querydate = "and DATE_FORMAT(effective_esk_date,'%Y-%m') >=  DATE_FORMAT(CURRENT_DATE,'%Y-%m')";
        }else{
            $querydate = "and DATE_FORMAT(effective_esk_date,'%Y-%m') >  DATE_FORMAT(CURRENT_DATE,'%Y-%m')";
        }
        
        // (status = "published" AND sync_status = 1 AND id_reason is not NULL) OR
        $sql = 'SELECT * FROM esklist_group_reason_v WHERE '.$query_where.' and (sync_status = 1 or flag_update =1) '.$querydate.''; // sprint 4

        $data = Yii::$app->db->createCommand($sql)->queryAll();
        //$data = EskLists::find()->where($query_where)->andWhere('status = "published" AND sync_status = 1')->orderBy('sync_date DESC')->limit(5000)->all();
        $totalData = count($data);
        $totalFiltered = $totalData;

        if (!empty($requestData['search']['value'])){
            $sql.=" AND ( nik LIKE '%" . $requestData['search']['value'] . "%' ";
            $sql.=" OR nama LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR about_esk LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR number_esk LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR effective_esk_date LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR sync_date LIKE '%" . $requestData['search']['value'] . "%'";
            // add by faqih
            $sql.=" OR groups_reason LIKE '%" . $requestData['search']['value'] . "%'";
            $sql.=" OR reason LIKE '%" . $requestData['search']['value'] . "%')";
            // end
        }
        $data = Yii::$app->db->createCommand($sql)->queryAll();
        $totalFiltered = count($data);

        $sql.=" ORDER BY sync_date DESC LIMIT ".$start." ,".$length." ";
        $result = Yii::$app->db->createCommand($sql)->queryAll();

        $data = array();
        foreach($result as $key => $row){
            $action =  Html::a('<span class="fa fa-file-pdf-o" style="color:brown;"></span>', ['preview','id'=>$row["id"],'flag' => 'synced'], ['title' => 'preview']); 
            
            //validate flag backdate
            $tgl_esk = date("Y-m-d",strtotime($row["effective_esk_date"]));
            $sync_date = empty($row["sync_date"]) ? date("Y-m-d") : date("Y-m-d",strtotime($row["sync_date"]));
            $selisih = abs(strtotime($sync_date) - strtotime($tgl_esk));
            $days = floor($selisih / (60*60*24));
            if ($days <= 2) {
                $color_flag = "green";
            } elseif ($days > 2 && $days <= 5) {
                $color_flag = "yellow";
            } else {
                $color_flag = "red";
            }
            $flag_backdate = (strtotime($sync_date) > strtotime($tgl_esk)) ? " <i class='fa fa-tag' style='color:".$color_flag."' title='backdate'></i>" : ""; 

            // add by faqih sprint 4
            $flg_update = '';
            if($row["flag_update"] == 1){
                $flg_update = 'Yes';
            }elseif($row["flag_update"] == 0){
                $flg_update = 'No';
            }
            // end 

            $nestedData = array();
            $nestedData[] = $row["id"];
            $nestedData[] = $row["nik"];
            $nestedData[] = $row["nama"];
            $nestedData[] = $row["about_esk"];
            $nestedData[] = $row["number_esk"];
            $nestedData[] = $row["groups_reason"];
            $nestedData[] = $row["reason"];
            $nestedData[] = $flg_update; // sprint 4
            $nestedData[] = empty($row["effective_esk_date"]) ? "-" : date("d-M-Y",strtotime($row["effective_esk_date"])).$flag_backdate;
            $nestedData[] = empty($row["sync_date"]) ? "-" : date("d-M-Y",strtotime($row["sync_date"]));
            $nestedData[] = empty($row["sync_result"]) ? "-" : $row["sync_result"];
            $nestedData[] = $action;
            $data[]       = $nestedData;
        }

        $json_data = array(
            "draw" => $draw, 
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data
        );
    
        return \yii\helpers\Json::encode($json_data);
    }

    public function exportData($model, $module){
        $spreadsheet = new Spreadsheet();

        // Set document properties
        $spreadsheet->getProperties()->setCreator('Arif Nur Rahman')
        ->setLastModifiedBy('Arif Nur Rahman')
        ->setTitle('Export eSK Data to Excel');

        // Add Header data
        $styleHeaderArray = array(
            'font'  => array(
                'bold'  => true,
                'size'  => 16
            )
        );

        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A1', $module);
        $spreadsheet->setActiveSheetIndex(0)->getStyle("A1")->applyFromArray($styleHeaderArray);
        $spreadsheet->setActiveSheetIndex(0)->mergeCells('A1:W1');

        //set column width
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('P')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(15);            
        $spreadsheet->getActiveSheet()->getColumnDimension('R')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(15);

        //new field for new gaji
        $spreadsheet->getActiveSheet()->getColumnDimension('T')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('U')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('V')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('W')->setWidth(17)->setAutoSize(true);

        $spreadsheet->getActiveSheet()->getColumnDimension('X')->setWidth(12); 
        $spreadsheet->getActiveSheet()->getColumnDimension('Y')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('Z')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('AA')->setWidth(25); 
        $spreadsheet->getActiveSheet()->getColumnDimension('AB')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('AC')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('AD')->setWidth(17);

        //set header table data
        $styleThArray = [
            'font'  => [
                'bold'  => true,
                'color' => array('rgb' => 'FFFFFF'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A3', 'Code');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('B3', 'Type');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('C3', 'NIK');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('D3', 'Name');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('E3', 'About');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('F3', 'eSK Number');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('G3', 'Old Position');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('H3', 'New Position');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('I3', 'Old Organization');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('J3', 'New Organization');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('K3', 'Old BP');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('L3', 'New BP');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('M3', 'Old BI');           
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('N3', 'New BI');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('O3', 'Old Area');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('P3', 'New Area');            
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('Q3', 'Authority Area');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('R3', 'Old City');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('S3', 'New City');  
        
        //NEW FIELD GAJI
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('T3', 'New Basic Salary'); 
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('U3', 'New Cost of Living Allowance'); 
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('V3', 'New Positional Allowance'); 
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('W3', 'New Recomposition Allowance'); 
        
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('X3', 'Status'); 
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('Y3', 'Sync Date');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('Z3', 'Flag Update');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AA3', 'Log Sync');    
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AB3', 'Effective Date');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AC3', 'Approved Date');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AD3', 'Created Date');

        $spreadsheet->setActiveSheetIndex(0)->getStyle("A3:AD3")->applyFromArray($styleThArray);
        $spreadsheet->getActiveSheet()->getStyle('A3:AD3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('8F8F89');

        //render data
        $styleCenterArray = [
            'font'  => [
                'color' => array('rgb' => '000000'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText'  => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        $styleNormalArray = [
            'font'  => [
                'color' => array('rgb' => '000000'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText'  => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        $i = 4;
        foreach($model as $rows){
            //set datanya
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('A'.$i, $rows['code_template']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('B'.$i, $rows['tipe']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('C'.$i, $rows['nik']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('D'.$i, $rows['nama']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('E'.$i, $rows['about_esk']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('F'.$i, $rows['number_esk']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('G'.$i, $rows['old_position']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('H'.$i, $rows['new_position']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('I'.$i, $rows['old_organization']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('J'.$i, $rows['new_organization']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('K'.$i, $rows['old_bp']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('L'.$i, $rows['new_bp']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('M'.$i, $rows['old_bi']);              
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('N'.$i, $rows['new_bi']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('O'.$i, $rows['old_area']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('P'.$i, $rows['new_area']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('Q'.$i, $rows['authority']);                
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('R'.$i, $rows['old_kota']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('S'.$i, $rows['new_kota']);

            //new field salary
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('T'.$i, empty($rows['gaji_dasar_nss'])? '0':number_format($rows['gaji_dasar_nss'], 0));
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('U'.$i, empty($rows['tbh_nss'])? '0':number_format($rows['tbh_nss'], 0));
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('V'.$i, empty($rows['tunjab_nss'])? '0':number_format($rows['tunjab_nss'], 0));
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('W'.$i, empty($rows['tunjangan_rekomposisi_nss'])? '0':number_format($rows['tunjangan_rekomposisi_nss'], 0));

            $spreadsheet->setActiveSheetIndex(0)->setCellValue('X'.$i, $rows['wordingApproved']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('Y'.$i, $rows['sync_date']);

            // add by faqih sprint 4 UT
            $flg_update = '';
            if($rows['flag_update'] == 1){
            	$flg_update = 'Yes';
            }else{
            	$flg_update = 'No';
            }
            // end

            $spreadsheet->setActiveSheetIndex(0)->setCellValue('Z'.$i, $flg_update);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AA'.$i, $rows['sync_result']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AB'.$i, $rows['effective_esk_date']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AC'.$i, $rows['approved_esk_date']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AD'.$i, $rows['created_at']);

            //set stylenya
            $spreadsheet->setActiveSheetIndex(0)->getStyle("A".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("B".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("C".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("D".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("E".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("F".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("G".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("H".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("I".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("J".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("K".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("L".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("M".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("N".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("O".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("P".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("Q".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("R".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("S".$i)->applyFromArray($styleCenterArray);

            //new field salary
            $spreadsheet->setActiveSheetIndex(0)->getStyle("T".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("U".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("V".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("W".$i)->applyFromArray($styleCenterArray);

            $spreadsheet->setActiveSheetIndex(0)->getStyle("X".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("Y".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("Z".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AA".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AB".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AC".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AD".$i)->applyFromArray($styleCenterArray);

            $i++;
        }

        //set date and time
        $styleTimeArray = [
            'font'  => [
                'color' => array('rgb' => 'BFBFBF'),
                'size'  => 9
            ],
        ];
        $dateTimeExport = date("d F Y")." - Time: ".date("H:i");
        $counter = ++$i;
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A'.$counter, $dateTimeExport);
        $spreadsheet->setActiveSheetIndex(0)->getStyle("A".$counter)->applyFromArray($styleTimeArray);

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Export of '.$module.'.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /*EJES 021024*/
    public function exportDataTypeTwo($model){

        $spreadsheet = new Spreadsheet();
        
        // Set document properties
        $spreadsheet->getProperties()->setCreator('Arif Nur Rahman')
        ->setLastModifiedBy('Arif Nur Rahman')
        ->setTitle('Export eSK Data to Excel');

        // Add Header data
        $styleHeaderArray = array(
            'font'  => array(
                'bold'  => true,
                'size'  => 16
            )
        );

        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A1', 'E-SK Data');
        $spreadsheet->setActiveSheetIndex(0)->getStyle("A1")->applyFromArray($styleHeaderArray);
        $spreadsheet->setActiveSheetIndex(0)->mergeCells('A1:Z1');

        //set column width
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('P')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(15);            
        $spreadsheet->getActiveSheet()->getColumnDimension('R')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('T')->setWidth(12); 
        $spreadsheet->getActiveSheet()->getColumnDimension('U')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('V')->setWidth(17)->setAutoSize(true); //GRADE - Sept 2024
        $spreadsheet->getActiveSheet()->getColumnDimension('W')->setWidth(17)->setAutoSize(true); //JOB CATEGORY - Sept 2024
        // add by faqih note frs add field export data to excel 26042024
        $spreadsheet->getActiveSheet()->getColumnDimension('X')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('Y')->setWidth(20); 
        $spreadsheet->getActiveSheet()->getColumnDimension('Z')->setWidth(20);
        // add by ejes, request field tambahan 04092024
        $spreadsheet->getActiveSheet()->getColumnDimension('AA')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AB')->setWidth(20); 
        $spreadsheet->getActiveSheet()->getColumnDimension('AC')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AD')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AE')->setWidth(20); 
        $spreadsheet->getActiveSheet()->getColumnDimension('AF')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AG')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AH')->setWidth(20); 
        $spreadsheet->getActiveSheet()->getColumnDimension('AI')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AJ')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AK')->setWidth(20);
        $spreadsheet->getActiveSheet()->getColumnDimension('AL')->setWidth(20);
        

        //set header table data
        $styleThArray = [
            'font'  => [
                'bold'  => true,
                'color' => array('rgb' => 'FFFFFF'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        
        // add by ejes, request field tambahan 04092024
        // nik atasan, nama atasan, grade, job category
        //  gaji dasar, tunjangan kemahalan, tunjangan dasar, tunjangan jabatan

        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A3', 'Code');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('B3', 'Type');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('C3', 'NIK');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('D3', 'Name');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('E3', 'About');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('F3', 'eSK Number');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('G3', 'Old Position');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('H3', 'New Position');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('I3', 'Old Organization');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('J3', 'New Organization');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('K3', 'Old BP');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('L3', 'New BP');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('M3', 'Old BI');           
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('N3', 'New BI');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('O3', 'Old Area');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('P3', 'New Area');            
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('Q3', 'Authority Area');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('R3', 'Old City');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('S3', 'New City');  

        //new field 04092024 Start >>
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('T3', 'NIK Atasan');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('U3', 'Nama Atasan');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('V3', 'Grade');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('W3', 'Job Category');  
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('X3', 'Gaji Dasar');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('Y3', 'Tunjangan Kemahalan');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('Z3', 'Tunjangan Dasar');           
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AA3', 'Tunjangan Jabatan');  
        //new field END
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AB3', 'Status');     
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AC3', 'Effective Date');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AD3', 'Approved Date');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AE3', 'Created Date');
        // add by faqih note frs add field export data to excel 26042024
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AF3', 'Sync Status');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AG3', 'Sync Date');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('AH3', 'Log');
		$spreadsheet->setActiveSheetIndex(0)->setCellValue('AI3', 'old_directorate');
		$spreadsheet->setActiveSheetIndex(0)->setCellValue('AJ3', 'new_directorate');
		
        $spreadsheet->setActiveSheetIndex(0)->getStyle("A3:AJ3")->applyFromArray($styleThArray);
        $spreadsheet->getActiveSheet()->getStyle('A3:AJ3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('8F8F89');

        //render data
        $styleCenterArray = [
            'font'  => [
                'color' => array('rgb' => '000000'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText'  => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        $styleNormalArray = [
            'font'  => [
                'color' => array('rgb' => '000000'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText'  => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        $i = 4;
        foreach($model as $rows){
            // ejes 07092024
            //get data payroll ebs  
            $gjdsr =0; 
            $biayahidup =0;      
            $rekom =0;   
            $tunjab = 0;
            $jcategory = 'NA';
            $nmatasanbaru = 'NA';

            $atasan = Employee::find()->where('nik = "'.$rows['nik_new_atasan'].'"')->one();
            $nmatasanbaru = $atasan->nama;

            $salaryebs = Model::getSalaryEBS($rows['new_bi'],$rows['new_bp'],$rows['nik'],$rows['effective_esk_date'],$rows['id']); 
            $gjdsr = $salaryebs['gaji_dasar'];
            $biayahidup = $salaryebs['tunjangan_biaya_hidup'];
            $rekom = $salaryebs['tunjangan_rekomposisi'];
            $tunjab = $salaryebs['tunjangan_jabatan'];

            //get jobcategory
            $jcatebs = Model::getnewjobcatg($rows['new_position_id']);
            $jcategory= $jcatebs['job_category'];
            //set datanya
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('A'.$i, $rows['code_template']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('B'.$i, $rows['tipe']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('C'.$i, $rows['nik']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('D'.$i, $rows['nama']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('E'.$i, $rows['about_esk']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('F'.$i, $rows['number_esk']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('G'.$i, $rows['old_position']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('H'.$i, $rows['new_position']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('I'.$i, $rows['old_organization']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('J'.$i, $rows['new_organization']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('K'.$i, $rows['old_bp']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('L'.$i, $rows['new_bp']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('M'.$i, $rows['old_bi']);              
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('N'.$i, $rows['new_bi']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('O'.$i, $rows['old_area']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('P'.$i, $rows['new_area']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('Q'.$i, $rows['authority']);                
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('R'.$i, $rows['old_kota']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('S'.$i, $rows['new_kota']);

            // add by ejes, request field tambahan 12092024
            // nik atasan, nama atasan, grade, job category
            //  gaji dasar, tunjangan kemahalan, tunjangan dasar, tunjangan jabatan
                      
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('T'.$i, $rows['nik_new_atasan']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('U'.$i, $nmatasanbaru);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('V'.$i, $rows['grade']); 

            $spreadsheet->setActiveSheetIndex(0)->setCellValue('W'.$i, $jcategory);
            
            if($rows['gaji_dasar_nss']==0){
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('X'.$i, $gjdsr);
            }else{
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('X'.$i, $rows['gaji_dasar_nss']);
            }

            if($rows['tbh_nss']==0){
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('Y'.$i, $biayahidup);
            }else{
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('Y'.$i, $rows['tbh_nss']);
            }
            
            if($rows['tunjangan_rekomposisi_nss']==0){
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('Z'.$i, $rekom);
            }else{
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('Z'.$i, $rows['tunjangan_rekomposisi_nss']);
            }

            if($rows['tunjab_nss']==0){
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('AA'.$i, $tunjab);
            }else{
                $spreadsheet->setActiveSheetIndex(0)->setCellValue('AA'.$i,$rows['tunjab_nss']);
            }

            
            //new field END

            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AB'.$i, $rows['wordingApproved']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AC'.$i, $rows['effective_esk_date']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AD'.$i, $rows['approved_esk_date']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AE'.$i, $rows['created_at']);
            // add by faqih note frs add field export data to excel 26042024
            $syn_sts = '';
            if($rows['sync_status'] == 1){
                $syn_sts = 'Yes';
            }
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AF'.$i, $syn_sts);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AG'.$i, $rows['sync_date']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('AH'.$i, $rows['sync_result']);
			$spreadsheet->setActiveSheetIndex(0)->setCellValue('AI'.$i, $rows['old_directorate']);
			$spreadsheet->setActiveSheetIndex(0)->setCellValue('AJ'.$i, $rows['new_directorate']);

            //set stylenya
            $spreadsheet->setActiveSheetIndex(0)->getStyle("A".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("B".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("C".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("D".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("E".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("F".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("G".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("H".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("I".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("J".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("K".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("L".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("M".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("N".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("O".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("P".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("Q".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("R".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("S".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("T".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("U".$i)->applyFromArray($styleCenterArray);
            //edit style  and new field Start
            $spreadsheet->setActiveSheetIndex(0)->getStyle("V".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("W".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("X".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("Y".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("Z".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AA".$i)->applyFromArray($styleCenterArray);
           

            // add by faqih note frs add field export data to excel 26042024
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AB".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AC".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AD".$i)->applyFromArray($styleCenterArray);
			$spreadsheet->setActiveSheetIndex(0)->getStyle("AE".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AF".$i)->applyFromArray($styleCenterArray);
			$spreadsheet->setActiveSheetIndex(0)->getStyle("AG".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AH".$i)->applyFromArray($styleCenterArray);
			$spreadsheet->setActiveSheetIndex(0)->getStyle("AI".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("AJ".$i)->applyFromArray($styleNormalArray);

            $i++;
        }

        //set date and time
        $styleTimeArray = [
            'font'  => [
                'color' => array('rgb' => 'BFBFBF'),
                'size'  => 9
            ],
        ];
        $dateTimeExport = date("d F Y")." - Time: ".date("H:i");
        $counter = ++$i;
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A'.$counter, $dateTimeExport);
        $spreadsheet->setActiveSheetIndex(0)->getStyle("A".$counter)->applyFromArray($styleTimeArray);

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Export of Data ESK.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    public function exportDataTypeThree($model){
        $spreadsheet = new Spreadsheet();

        // Set document properties
        $spreadsheet->getProperties()->setCreator('Arif Nur Rahman')
        ->setLastModifiedBy('Arif Nur Rahman')
        ->setTitle('Export eSK Data to Excel');

        // Add Header data
        $styleHeaderArray = array(
            'font'  => array(
                'bold'  => true,
                'size'  => 16
            )
        );

        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A1', 'E-SK Data');
        $spreadsheet->setActiveSheetIndex(0)->getStyle("A1")->applyFromArray($styleHeaderArray);
        $spreadsheet->setActiveSheetIndex(0)->mergeCells('A1:T1');

        //set column width
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(17)->setAutoSize(true);
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(15);            
        $spreadsheet->getActiveSheet()->getColumnDimension('P')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(15);
        $spreadsheet->getActiveSheet()->getColumnDimension('R')->setWidth(12); 
        $spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(17);
        $spreadsheet->getActiveSheet()->getColumnDimension('T')->setWidth(17);

        //set header table data
        $styleThArray = [
            'font'  => [
                'bold'  => true,
                'color' => array('rgb' => 'FFFFFF'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A3', 'NIK');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('B3', 'Name');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('C3', 'About');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('D3', 'eSK Number');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('E3', 'Old Position');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('F3', 'New Position');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('G3', 'Old Organization');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('H3', 'New Organization');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('I3', 'Old BP');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('J3', 'New BP');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('K3', 'Old BI');           
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('L3', 'New BI');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('M3', 'Old Area');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('N3', 'New Area');            
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('O3', 'Authority Area');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('P3', 'Old City');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('Q3', 'New City');  
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('R3', 'Status');     
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('S3', 'Effective Date');
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('T3', 'Approved Date');

        $spreadsheet->setActiveSheetIndex(0)->getStyle("A3:T3")->applyFromArray($styleThArray);
        $spreadsheet->getActiveSheet()->getStyle('A3:T3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('8F8F89');

        //render data
        $styleCenterArray = [
            'font'  => [
                'color' => array('rgb' => '000000'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText'  => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        $styleNormalArray = [
            'font'  => [
                'color' => array('rgb' => '000000'),
                'size'  => 9
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText'  => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];

        $i = 4;
        foreach($model as $rows){
            //set datanya
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('A'.$i, $rows['nik']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('B'.$i, $rows['nama']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('C'.$i, $rows['about_esk']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('D'.$i, $rows['number_esk']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('E'.$i, $rows['old_position']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('F'.$i, $rows['new_position']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('G'.$i, $rows['old_organization']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('H'.$i, $rows['new_organization']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('I'.$i, $rows['old_bp']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('J'.$i, $rows['new_bp']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('K'.$i, $rows['old_bi']);              
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('L'.$i, $rows['new_bi']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('M'.$i, $rows['old_area']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('N'.$i, $rows['new_area']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('O'.$i, $rows['authority']);                
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('P'.$i, $rows['old_kota']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('Q'.$i, $rows['new_kota']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('R'.$i, $rows['wordingApproved']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('S'.$i, $rows['effective_esk_date']);
            $spreadsheet->setActiveSheetIndex(0)->setCellValue('T'.$i, $rows['approved_esk_date']);

            //set stylenya
            $spreadsheet->setActiveSheetIndex(0)->getStyle("A".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("B".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("C".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("D".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("E".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("F".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("G".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("H".$i)->applyFromArray($styleNormalArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("I".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("J".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("K".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("L".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("M".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("N".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("O".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("P".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("Q".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("R".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("S".$i)->applyFromArray($styleCenterArray);
            $spreadsheet->setActiveSheetIndex(0)->getStyle("T".$i)->applyFromArray($styleCenterArray);
            $i++;
        }

        //set date and time
        $styleTimeArray = [
            'font'  => [
                'color' => array('rgb' => 'BFBFBF'),
                'size'  => 9
            ],
        ];
        $dateTimeExport = date("d F Y")." - Time: ".date("H:i");
        $counter = ++$i;
        $spreadsheet->setActiveSheetIndex(0)->setCellValue('A'.$counter, $dateTimeExport);
        $spreadsheet->setActiveSheetIndex(0)->getStyle("A".$counter)->applyFromArray($styleTimeArray);

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Export of Data ESK.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }
}
