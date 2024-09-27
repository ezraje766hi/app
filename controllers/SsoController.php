<?php
namespace esk\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\Pagination;
use yii\web\NotFoundHttpException;
use common\components\Sso;
use yii\helpers\Url;
use common\models\LoginForm;
use common\models\User;
use esk\models\Employee;

/**
 * Site controller
 */
class SsoController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
						'actions' => ['sso-login','access-check'],
                        'allow' => true,
                    ],
                ],
            ],
        ];
    }
	
	public function actionSsoLogin($p,$t)
    {
		$u = Sso::validateToken($p);
		$red = base64_decode($t);

		if(!empty($u))
		{
			$model = new LoginForm();
			$model->username = $u;
			if($model->loginSSO())
			{
				return $this->redirect($red);
			}
			else
			{
				Yii::$app->user->returnUrl = $red;
				return $this->redirect(['site/login-page']);
			}
		} else {
			echo "Please open again from main page";
		}
    }
	
	public function actionAccessCheck()
    {
		$token = Yii::$app->request->get('token');

        //get token and check it
        if(empty($token)){
            throw new \yii\web\HttpException(404,'The token could not be found or empty.');
        }
        $nik = Sso::decryptedToken($token);

        //check if nik empty
        if(empty($nik)){
            throw new \yii\web\HttpException(404,'The token data could not be found.');
        }

        //check if can login
        $user = User::findOne(['nik' => $nik,'status' => '1']);
        $checkEmp = Employee::findOne(['nik' => $nik,'status' => 'AKTIF']);
        if(!empty($user) && !empty($checkEmp)){    
            \Yii::$app->user->login($user);
            return $this->redirect(['site/index']);
        }else{
            throw new \yii\web\HttpException(404,'The token user could not be found.');
        }
    }
}
