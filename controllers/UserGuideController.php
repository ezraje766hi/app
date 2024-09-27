<?php
namespace esk\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\web\UploadedFile;
use esk\models\Model;
/**
 * Site controller
 */
class UserGuideController extends Controller
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
                        'actions' => ['login', 'error'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['section', 'content','template','configapproval','mutation','authority','type','code','distance'],
                        'allow' => true,
                        'roles' => ['sysadmin','hc_staffing'],
                    ],
                    [
                        'actions' => ['generate', 'upload-ba','process','delivery','all'],
                        'allow' => true,
                        'roles' => ['sysadmin','hc_staffing','hcbp_account'],
                    ],
                    [
                        'actions' => ['approval', 'approved'],
                        'allow' => true,
                        'matchCallback' => function ($rule, $action) {
                            //validation count approval
                            $countReview = Model::countApproval() + Model::countDelivered();
                            if($countReview <= 0){
                                $visibleMenu = false;
                            }else{
                                $visibleMenu = true;
                            }

                            if($visibleMenu){
                                return $visibleMenu;
                            }

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

                            //check if role sysadmin
                            if(Yii::$app->user->can('sysadmin')){
                               return true; 
                            }else{
                                return false;
                            }
                        }
                    ],
                    [
                        'actions' => ['esk'],
                        'allow' => true,
                        'roles' => ['@'],
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

    public function actionViewGambar($nama){
        $file = Yii::getAlias('@esk/web/user_guide/' . $nama);
        return Yii::$app->response->sendFile($file, NULL, ['inline' => TRUE]);
    }

    //user guide untuk hc staffing/hcbp generate esk
    public function actionGenerate()
    {   
        return $this->render('guide-generate-esk');
    }

    //user guide untuk hc staffing/hcbp proses/review esk yang baru tergenerate    
    public function actionProcess()
    {   
        return $this->render('guide-process-esk');
    }

    //user guide untuk approval dan VP esk
    public function actionApproval()
    {   
        return $this->render('guide-approval-esk');
    }

    //user guide untuk approval melihat list esk yang pernah diapproved
    public function actionApproved()
    {   
        return $this->render('guide-approved-esk');
    }

    //user guide untuk hc staffing/hcbp delivery/review esk yang baru tergenerate    
    public function actionDelivery()
    {   
        return $this->render('guide-delivery-esk');
    }

    //user guide untuk employee melihat esk
    public function actionEsk()
    {   
        return $this->render('guide-my-esk');
    }

    //user guide untuk hc staffing/hcbp melihat semua esk
    public function actionAll()
    {   
        return $this->render('guide-all-esk');
    }

    //user guide untuk hc staffing/sysadmin untuk melakukan setting data
    public function actionSection()
    {   
        return $this->render('guide-section-esk');
    }

    public function actionContent()
    {   
        return $this->render('guide-content-esk');
    }

    public function actionMutation()
    {   
        return $this->render('guide-mutation-esk');
    }

    public function actionAuthority()
    {   
        return $this->render('guide-authority-esk');
    }

    public function actionType()
    {   
        return $this->render('guide-type-esk');
    }

    public function actionConfigapproval()
    {   
        return $this->render('guide-config-approval-esk');
    }

    public function actionTemplate()
    {   
        return $this->render('guide-template-esk');
    }

    public function actionCode()
    {   
        return $this->render('guide-code-esk');
    }

    public function actionDistance()
    {   
        return $this->render('guide-distance-esk');
    }
	
	//user guide untuk approval dan VP esk
    public function actionUploadBa()
    {   
        return $this->render('guide-upload-ba');
    }
	
	public function actionBeritaDirectorate($data)
    {   
		$this->layout = 'main-berita';
		if($data == "CEO's Office Directorate") {
			$data = "b2b";
		} elseif($data == "Sales Directorate" || $data == "Marketing Directorate") {
			$data = "b2c";
		}
		
		return $this->render('guide-berita-data', compact('data'));
    }
	
	public function actionUnduh($tipe, $directorate) 
	{ 

		if($tipe == "function" && $directorate == "b2b")
			$path = Yii::getAlias('@webroot').'/user_guide/' . 'b2b-function.doc';
		elseif($tipe == "structure" && $directorate == "b2b")
			$path = Yii::getAlias('@webroot').'/user_guide/' . 'b2b-structure.pdf';
		elseif($tipe == "function" && $directorate == "b2c")
			$path = Yii::getAlias('@webroot').'/user_guide/' . 'b2c-function.doc';
		elseif($tipe == "structure" && $directorate == "b2c")
			$path = Yii::getAlias('@webroot').'/user_guide/' . 'b2c-structure.pdf';
			
		if (file_exists($path)) {
			return Yii::$app->response->sendFile($path);
		}
	}
}
