<?php
namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use kartik\mpdf\Pdf;
use yii\web\NotFoundHttpException;

use common\models\Employee;
use common\models\LoginForm;
use esk\models\Model;
use esk\models\EskListsSearch;
use esk\models\EskLists;
use esk\models\EskTemplateMaster;
use esk\models\AuthAssignment;

/**
 * Site controller
 */
class SiteController extends Controller
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
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['login','login-default','error'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function actionTestCareer(){
        //posting career
        $data["caption"] = "this is posting career";
        $data["type_posting"] = "Career";
        $data["career_id"] = 103;

        $header = array(
            "xusernik: 80125",
            "Content-Type: multipart/form-data"
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://10.250.200.153:8000/smilley/api/v1/posts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        var_dump($response);
        var_dump($err);
        var_dump($http_status);
        exit();
    }

    public function actionUpdateNumber(){
        $esk_lists_data = EskLists::find()->orderBy("created_at ASC")->all();
        $i = 1;
        foreach($esk_lists_data as $esk){
            $data_esk = EskLists::findOne($esk->id);
            $data_esk->sequence = $i;
            $data_esk->number_esk = "0" . $i . "" . $data_esk->no_esk . "" . Model::getMonthYear($data_esk->month_id, $data_esk->year_id);
            $data_esk->save();
            $i++;
        }
        echo "Penulisan ulang nomor eSK selesai, silakan cek kembali";
        die();
    }
    
    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {   
        $searchModel = new EskListsSearch();
        $dataProvider = $searchModel->findMyEsk(Yii::$app->user->identity->nik);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionPreview($id)
    {   
		$employee = Yii::$app->user->identity->employee;
        $model = $this->findModel($id);
		if($model->nik != $employee->nik)
		{
			$auth = AuthAssignment::findOne(['user_id' => Yii::$app->user->id, 'item_name' => ['hc_staffing', 'hcbp_account']]);
			if(empty($auth))
			{
				throw new NotFoundHttpException('The requested page does not exist.');
			}
		}
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
        ]);
    }

    public function actionPrint($id)
    {   
        $id = yii::$app->request->get('id');
        $flag = yii::$app->request->get('flag');
        $model = $this->findModel($id);
		
		$employee = Yii::$app->user->identity->employee;
        $model = $this->findModel($id);
		if($model->nik != $employee->nik)
		{
			$auth = AuthAssignment::findOne(['user_id' => Yii::$app->user->id, 'item_name' => ['hc_staffing', 'hcbp_account']]);
			if(empty($auth))
			{
				throw new NotFoundHttpException('The requested page does not exist.');
			}
		}
		
        //logging data
        Model::saveLog(Yii::$app->user->identity->username, "Print eSK with ID ".$model->id);

        $file_name = "";

        if($flag == 1){
            $esk_template = EskTemplateMaster::find()->where(['code_template' => $model->code_template])->one();
            $all_content = Model::setEskData($model->id,$model->about_esk,$model->number_esk,$model->content_esk,$model->city_esk,$model->decree_nama,$model->decree_nik,$model->decree_title,$model->is_represented,$model->represented_title,$model->approved_esk_date,$file_name,"print","1");
            
            if(empty($esk_template)){
                //print default tanpa page break dan footer
                $pdf = new Pdf([
                    'mode' => Pdf::MODE_UTF8, 
                    //'format' => Pdf::FORMAT_FOLIO,
					'format' => [230, 300],				
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
                            //'format' => Pdf::FORMAT_FOLIO, 
                            'format' => [230, 300],		
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
                            //'format' => Pdf::FORMAT_FOLIO, 
                            'format' => [230, 300],		
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
                        //'format' => Pdf::FORMAT_FOLIO, 
                        'format' => [230, 300],		
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
            return $this->renderPartial('print_plain', [
                'content' => $all_content,
            ]);
        }
    }

    /**
     * Login action.
     *
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        $error_log = 0;
        if ($model->load(Yii::$app->request->post())) {
            if($model->login()){
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "User login into application");
                return $this->goBack();
            }else{
                $error_log = 1;
            }
        }

        return $this->render('login', [
            'model' => $model,
            'error_log' => $error_log
        ]);
    }
	
	public function actionLoginDefault()
    {

        $error_log = 0;
	
		$this->layout = 'main-login';
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
			return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
				'error_log' => $error_log
            ]);
        }
    }

    /**
     * Logout action.
     *
     * @return string
     */
    public function actionLogout()
    {
        //logging data
        Model::saveLog(Yii::$app->user->identity->username, "User logout from application");
        Yii::$app->user->logout();
        return $this->goHome();
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
	
	public function actionDirectoratelist($q = null, $id = 0) {
		Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		
		$out = ['results' => ['id' => '', 'text' => '']];
		
		if (!is_null($q)) {		
			$data = Employee::find()->where(['status' => 'AKTIF'])->andWhere(['like', 'directorate', $q])->groupBy(['directorate'])->limit(100)->all();
			
			foreach($data as $rows)
				$tmp[] = ["id" => $rows->directorate, "text" => $rows->directorate];
			
			$out['results'] = empty($tmp) ? ['id' => '', 'text' => ''] : $tmp;
		}elseif ($id > 0) {
			$out['results'] = ['id' => $id, 'text' => Employee::findOne(['nik' => $id])->directorate];
		}
		
		return $out;
	}

	public function actionArealist($q = null, $id = 0) {
		Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		
		$out = ['results' => ['id' => '', 'text' => '']];
		
		if (!is_null($q)) {		
			$data = Employee::find()->where(['status' => 'AKTIF'])->andWhere(['like', 'area', $q])->groupBy(['area'])->limit(100)->all();
			
			foreach($data as $rows)
				$tmp[] = ["id" => $rows->area, "text" => $rows->area];
			
			$out['results'] = empty($tmp) ? ['id' => '', 'text' => ''] : $tmp;
		}elseif ($id > 0) {
			$out['results'] = ['id' => $id, 'text' => Employee::findOne(['nik' => $id])->area];
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
	
	public function actionSendSimpleEmail()
	{
		$employee  	= Yii::$app->user->identity->employee;
		if(Yii::$app->params['EMAIL_FROM']){
			$sender_email 	= (Yii::$app->params['EMAIL_FROM']) ? Yii::$app->params['EMAIL_FROM'] : Yii::$app->params['adminEmail'];
			$receiver_email = (Yii::$app->params['DUMMY_MAIL']) ? Yii::$app->params['DUMMY_TO_MAIL'] : $employee->email;
			

			try {
					Yii::$app->mailer->compose()
					->setFrom($sender_email)
					->setTo($receiver_email)
					->setSubject('Testing Email Aplikasi ESK - ' . $employee->nama)
					->setTextBody('Plain text content. YII2 Application')
					->setHtmlBody('<b>Test Email ESK</b>')
					->send();
			} catch (\Swift_TransportException $e) { }
			
			echo "sender : " . $sender_email . " | receiver : " . $receiver_email;
		}
		

		return false;
	}
	
	public function actionSendSimpleEmailExternal()
	{
		$employee  	= Yii::$app->user->identity->employee;
		if(Yii::$app->params['EMAIL_FROM']){
			$sender_email 	= (Yii::$app->params['EMAIL_FROM']) ? Yii::$app->params['EMAIL_FROM'] : Yii::$app->params['adminEmail'];
			$receiver_email = (Yii::$app->params['DUMMY_MAIL']) ? Yii::$app->params['DUMMY_TO_MAIL'] : $employee->email;
			

			try {
					Yii::$app->mailer->compose()
					->setFrom($sender_email)
					->setTo('830034@TELKOM.CO.ID')
					->setBcc(['joemuhtadi@gmail.com','jundi_muhtadi@telkomsel.co.id', 'arnold_b_dualembang@telkomsel.co.id', 'Fatkhureza@telkomsel.co.id'])
					->setSubject('Testing Email Aplikasi ESK - ' . $employee->nama)
					->setTextBody('Plain text content. YII2 Application')
					->setHtmlBody('<b>Test Email ESK</b>')
					->send();
			} catch (\Swift_TransportException $e) { }
			
			try {
					Yii::$app->mailer->compose()
					->setFrom($sender_email)
					->setTo('880066@TELKOM.CO.ID')
					->setBcc(['joemuhtadi@gmail.com','jundi_muhtadi@telkomsel.co.id', 'arnold_b_dualembang@telkomsel.co.id', 'Fatkhureza@telkomsel.co.id'])
					->setSubject('Testing Email Aplikasi ESK - ' . $employee->nama)
					->setTextBody('Plain text content. YII2 Application')
					->setHtmlBody('<b>Test Email ESK</b>')
					->send();
			} catch (\Swift_TransportException $e) { }
			
			try {
					Yii::$app->mailer->compose()
					->setFrom($sender_email)
					->setTo('940325@TELKOM.CO.ID')
					->setBcc(['joemuhtadi@gmail.com','jundi_muhtadi@telkomsel.co.id', 'arnold_b_dualembang@telkomsel.co.id', 'Fatkhureza@telkomsel.co.id'])
					->setSubject('Testing Email Aplikasi ESK - ' . $employee->nama)
					->setTextBody('Plain text content. YII2 Application')
					->setHtmlBody('<b>Test Email ESK</b>')
					->send();
			} catch (\Swift_TransportException $e) { }
			
			echo "sender : " . $sender_email . " | receiver : " . $receiver_email;
		}
		

		return false;
	}
	
	public function actionSendAlertNotification($jenis)
	{
		$employee  	    = Yii::$app->user->identity->employee;
		$sender_email   = 'No_ReplyHRIS@telkomsel.co.id';
		$receiver_email = $employee->email;
		
		if($jenis == "resign") {
			$data = Employee::find()->where(['nik' => '97040'])->one();
			
			try{
					Yii::$app->mailer->compose('alert-resign-notification', ['data' => $data])
					->setFrom($sender_email)
					->setTo($receiver_email)
					->setSubject('HCIS Alert : Terminate ' . $data->person_id . '[' . $data->nik . ']') 
					->send();
				} catch(\Swift_TransportException $e){
			}

		} elseif($jenis == "rotasi") {
			$data = Employee::find()->where(['nik' => '73335'])->one();
			
			try{
					Yii::$app->mailer->compose('alert-rotasi-notification', ['data' => $data])
					->setFrom($sender_email)
					->setTo($receiver_email)
					->setSubject('HCIS Alert : New Assignment ' . $data->person_id . '[' . $data->nik . ']') 
					->send();
				} catch(\Swift_TransportException $e){
			}

		} elseif($jenis == "contract") {
			$data = Employee::find()->where(['nik' => 'T218074'])->one();
			
			try{
					Yii::$app->mailer->compose('alert-contract-notification', ['data' => $data])
					->setFrom($sender_email)
					->setTo($receiver_email)
					->setSubject('HCIS Alert : Contract Termination ' . $data->person_id . '[' . $data->nik . ']') 
					->send();
				} catch(\Swift_TransportException $e){
			}

		} elseif($jenis == "reorganization") {
			$data = Employee::find()->where(['nik' => '86047'])->one();
			
			try{
					Yii::$app->mailer->compose('alert-reorganization-notification', ['data' => $data])
					->setFrom($sender_email)
					->setTo($receiver_email)
					->setSubject('HCIS Alert : New Assignment ' . $data->person_id . '[' . $data->nik . ']') 
					->send();
				} catch(\Swift_TransportException $e){
			}

		}
		
		
		echo "sender : " . $sender_email . " | receiver : " . $receiver_email;
		
		

		return false;
	}
}
