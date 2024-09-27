<?php
namespace esk\controllers;

use Yii;
use common\models\User;
use esk\models\Model;
use esk\models\Employee;
use esk\models\UserData;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\data\ActiveDataProvider;

/**
 * EskSectionController implements the CRUD actions for EskSection model.
 */
class AccessListsController extends Controller
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
     * Lists all EskSection models.
     * @return mixed
     */
    public function actionIndex()
    {
        $dataActiveProvider = new ActiveDataProvider([
            'query' => User::find()->orderBy('updated_at DESC'),
            'pagination' => false,
        ]);
        
        return $this->render('index', [
            'dataProvider' => $dataActiveProvider,
        ]);
    }

    /**
     * Creates a new EskSection model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {   
        $model = new UserData();
        if ($model->load(Yii::$app->request->post())) {
            $result = $model->signup();
            if ($result == "1") {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Create a new user with username ".$model->username);
                Yii::$app->session->setFlash('success', "User data successfully created."); 
                return $this->redirect(['access-lists/index']);
            } else {
                Yii::$app->session->setFlash('error', "User data was not saved because ".implode(",",$result));
                return $this->redirect(['access-lists/index']);
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing EskSection model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {   
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $request = Yii::$app->request->post();

            //validate password
            if(!empty($request['User']['password_hash'])){
                //has new password
                $model->password_hash = Yii::$app->security->generatePasswordHash($request['User']['password_hash']);
            }else{
                $model->password_hash = $model->password_hash;
            }
            $model->email = $request['User']['email'];
            $model->nik = $request['User']['nik'];
            
            if ($model->save()) {
                //logging data
                Model::saveLog(Yii::$app->user->identity->username, "Update user data with ID ".$model->id);
          
                Yii::$app->session->setFlash('success', "User data successfully updated."); 
            } else {
                Yii::$app->session->setFlash('error', "User data was not updated.");
            }
            return $this->redirect(['access-lists/index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing EskSection model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete()) {
            //logging data
            Model::saveLog(Yii::$app->user->identity->username, "Delete user data for username ".$id);
          
            Yii::$app->session->setFlash('success', "User data successfully deleted."); 
        } else {
            Yii::$app->session->setFlash('error', "User data was not deleted.");
        }

        return $this->redirect(['index']);
    }

    /**
     * Finds the EskSection model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return EskSection the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionEmplist($q = null, $id = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            $query = new \yii\db\Query;
            $query->select(["nik AS id, CONCAT(nama, ' (', title, ')') AS text"])
                ->from('employee')
                ->where(['like', 'nama', $q])
                ->orWhere(['like', 'title', $q])
                ->andWhere(['status' => 'AKTIF']);
            $command = $query->createCommand();
            $data = $command->queryAll();
            $out['results'] = array_values($data);
        }
        elseif ($id > 0) {
            $out['results'] = ['id' => $id, 'text' => Employee::find($id)->nama];
        }
        return $out;
    }
}
