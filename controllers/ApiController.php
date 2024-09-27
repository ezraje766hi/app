<?php

namespace esk\controllers;

use esk\components\Helper;
use Yii;
use yii\web\Controller;
use \yii\web\Response;
use yii\db\Query;
use kartik\mpdf\Pdf;
use esk\models\ApiUser;
use esk\models\EskLists;
use esk\models\EskAcknowledgeLists;
use esk\models\EskTemplateMaster;
use esk\models\EskFlagData;
use esk\models\Model;
use esk\models\Employee;
use yii\web\HttpException;

/**
 * Api controller
 */
class ApiController extends Controller
{
    public function beforeAction($action)
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->controller->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function behaviors()
    {
        return [
            'basicAuth' => [
                'class' => \yii\filters\auth\HttpBasicAuth::className(),
                'auth' => function ($username, $password) {
                    return ApiUser::authenticateLogin($username, $password);
                },
            ],
        ];
    }

    public function getViewPath()
    {
        return Yii::getAlias('@esk/mail/');
    }

    public function errorMessage($message, $status = '500', $name = null)
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->statusCode = $status;
        $response->data = [
            'name' => empty($name) ? "Unauthorized" : $name,
            'message' => $message,
            'code' => 0,
            'status' => $status,
            'type' => "yii\\base\\HttpException"
        ];

        echo $response->send();
        exit;
    }

    public function successMessageMeta($withTotal = false, $totalData = 0, $message, $status = '200', $title = 'success')
    {
        if ($withTotal) {
            $data = [
                'data' => $message,
                'meta' => [
                    'code' => 2001,
                    'message' => $title,
                    'total' => $totalData
                ]
            ];
        } else {
            $data = [
                'data' => $message,
                'meta' => [
                    'code' => 2001,
                    'message' => $title
                ]
            ];
        }
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->statusCode = $status;
        $response->data = $data;

        echo $response->send();
        exit;
    }

    public function errorMessageMeta($message, $status = '500', $title = 'something is wrong, report to support team')
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->statusCode = $status;
        $response->data = [
            'error' => $message,
            'meta' => [
                'code' => 5001,
                'message' => $title
            ]
        ];

        echo $response->send();
        exit;
    }

    public function actionMyEsk()
    {
        $nik = !empty(Yii::$app->request->post('nik')) ? Yii::$app->request->post('nik') : null;
        $headerData = Yii::$app->request->headers;
        if ($headerData->has('xusernik')) {
            $nik_header = $headerData->get('xusernik');
            if (strcmp($nik, $nik_header) !== 0) {
                return $this->errorMessage('You are not authorized to process this request', '401');
            }
        } else {
            $result['code'] = 0;
            $result['description'] = "Header of xusernik not found, please check again your configuration";

            return $result;
            exit();
        }

        $model = EskLists::find()->select('id, nik, nama, about_esk, number_esk, tracking, effective_esk_date, tipe, old_position, 
        new_position,old_organization, new_organization, old_title, new_title, old_section, new_section, old_department, new_department, 
        old_division, new_division, old_bgroup, new_bgroup, old_egroup, new_egroup, old_directorate, new_directorate, 
        old_area, new_area, old_bp, new_bp, old_bi, new_bi, old_kota, new_kota, gaji_dasar_nss, tbh_nss,
        tunjab_nss, tunjangan_rekomposisi_nss')
            ->where(['nik' => $nik])->andWhere(['status' => 'published'])->orderBy('updated_at ASC')->all();
        if (!empty($model)) {
            $data = [];
            foreach ($model as $row) {
                $temp = [];
                $temp['id'] = $row->id;
                $temp['nik'] = $row->nik;
                $temp['nama'] = $row->nama;
                $temp['about_esk'] = strip_tags($row->about_esk);
                $temp['number_esk'] = $row->number_esk;
                $temp['tracking'] = $row->tracking;
                $temp['effective_esk_date'] = $row->effective_esk_date;
                $temp['tipe'] = $row->tipe;
                $temp['old_position'] = $row->old_position;
                $temp['new_position'] = $row->new_position;
                $temp['old_organization'] = $row->old_organization;
                $temp['new_organization'] = $row->new_organization;
                $temp['old_title'] = $row->old_title;
                $temp['new_title'] = $row->new_title;
                $temp['old_section'] = $row->old_section;
                $temp['new_section'] = $row->new_section;
                $temp['old_department'] = $row->old_department;
                $temp['new_department'] = $row->new_department;
                $temp['old_division'] = $row->old_division;
                $temp['new_division'] = $row->new_division;
                $temp['old_bgroup'] = $row->old_bgroup;
                $temp['new_bgroup'] = $row->new_bgroup;
                $temp['old_egroup'] = $row->old_egroup;
                $temp['new_egroup'] = $row->new_egroup;
                $temp['old_directorate'] = $row->old_directorate;
                $temp['new_directorate'] = $row->new_directorate;
                $temp['old_area'] = $row->old_area;
                $temp['new_area'] = $row->new_area;
                $temp['old_bp'] = $row->old_bp;
                $temp['new_bp'] = $row->new_bp;
                $temp['old_bi'] = $row->old_bi;
                $temp['new_bi'] = $row->new_bi;
                $temp['old_kota'] = $row->old_kota;
                $temp['new_kota'] = $row->new_kota;
                $temp['gaji_dasar_nss'] = empty($row->gaji_dasar_nss) ? "0" : $row->gaji_dasar_nss;
                $temp['tbh_nss'] = empty($row->tbh_nss) ? "0" : $row->tbh_nss;
                $temp['tunjab_nss'] = empty($row->tunjab_nss) ? "0" : $row->tunjab_nss;
                $temp['tunjangan_rekomposisi_nss'] = empty($row->tunjangan_rekomposisi_nss) ? "0" : $row->tunjangan_rekomposisi_nss;
                $data[] = $temp;
            }
        } else {
            $data = [];
        }

        return $data;
    }

    public function actionPrintEsk()
    {
        $nik = !empty(Yii::$app->request->post('nik')) ? Yii::$app->request->post('nik') : null;
        $id = yii::$app->request->post('id');
        $model = EskLists::findOne($id);
        $headerData = Yii::$app->request->headers;
        if ($headerData->has('xusernik')) {
            $nik_header = $headerData->get('xusernik');
            if (strcmp($nik, $nik_header) !== 0) {
                return $this->errorMessage('You are not authorized to process this request', '401');
            }
        } else {
            $result['code'] = 0;
            $result['description'] = "Header of xusernik not found, please check again your configuration";

            return $result;
            exit();
        }

        if (empty($model)) {
            return $this->errorMessage('eSK Data is not found', '404');
        }

        //check apakah nik params = nik esk
        $model->check_nik = $nik;
        $model->nik_approver = $model->nik;
        $checkHead =  $model->approvalLists;
        if (strcmp($nik, $model->nik) !== 0 && $checkHead == false) {
            return $this->errorMessage('You are not authorized to process this request', '401');
        }

        //logging data
        Model::saveLog($nik, "Print eSK with ID " . $model->id);

        $file_name = "";
        $esk_template = EskTemplateMaster::find()->where(['code_template' => $model->code_template])->one();
        $all_content = Model::setEskData($model->id, $model->about_esk, $model->number_esk, $model->content_esk, $model->city_esk, $model->decree_nama, $model->decree_nik, $model->decree_title, $model->is_represented, $model->represented_title, $model->approved_esk_date, $file_name, "print", "1");

        if (empty($esk_template)) {
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
                'marginBottom' => 7,
                'marginHeader' => 8,
                'marginFooter' => 8,
                'filename' => "Surat Keputusan Nomor: " . $model->number_esk . " tentang " . $model->about_esk . ".pdf",
                'destination' => Pdf::DEST_STRING, //Pdf::DEST_DOWNLOAD
                'content' => $all_content,
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',
            ]);

            $pdfData = base64_encode($pdf->render());
        } else {
            //cek page break content 
            if ($esk_template->page_break_content != 0 || $esk_template->page_break_content != "0") {
                //get page break data
                $data_content = Model::setPageBreak($esk_template->id, $esk_template->page_break_content, $all_content);

                if ($data_content['is_pagebreak'] == 1) {
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
                        'marginBottom' => 7,
                        'marginHeader' => 8,
                        'marginFooter' => 8,
                        'filename' => "Surat Keputusan Nomor: " . $model->number_esk . " tentang " . $model->about_esk . ".pdf",
                        'destination' => Pdf::DEST_STRING, //Pdf::DEST_DOWNLOAD
                        'content' => str_replace("font-size:8pt", "font-size:9pt", $data_content['content']),
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',
                        'cssInline' => '
                            @media print{
                                .page-break{display: block;page-break-before: always;}
                            }
                        ',
                        'methods' => [
                            'SetHtmlFooter' => ['
                            <table width="100%" style="font-family:arial;font-size:7pt;">
                                <tr>
                                    <td width="40%">Halaman {PAGENO}/{nbpg}</td>
                                    <td width="60%" style="text-align: right;">eSK Nomor: ' . $model->number_esk . '</td>
                                </tr>
                            </table>
                            '],
                        ]
                    ]);

                    $pdfData = base64_encode($pdf->render());
                } else {
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
                        'marginBottom' => 7,
                        'marginHeader' => 8,
                        'marginFooter' => 8,
                        'filename' => "Surat Keputusan Nomor: " . $model->number_esk . " tentang " . $model->about_esk . ".pdf",
                        'destination' => Pdf::DEST_STRING, //Pdf::DEST_DOWNLOAD
                        'content' => $all_content,
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',
                    ]);

                    $pdfData = base64_encode($pdf->render());
                }
            } else {
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
                    'marginBottom' => 7,
                    'marginHeader' => 8,
                    'marginFooter' => 8,
                    'filename' => "Surat Keputusan Nomor: " . $model->number_esk . " tentang " . $model->about_esk . ".pdf",
                    'destination' => Pdf::DEST_STRING, //Pdf::DEST_DOWNLOAD
                    'content' => $all_content,
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.css',
                ]);

                $pdfData = base64_encode($pdf->render());
            }
        }

        $return['status'] = 'success';
        $return['message'] = 'data found';
        $return['data'] = $pdfData;

        return $return;
    }

    //--- approval esk start --//
    public function actionAcknowledgeData()
    {
        $data = Yii::$app->request->post();
        try {
            $esk_id = $data['workflow_id'];
            $nik_approver = empty($data['nik_approver']) ? null : $data['nik_approver'];

            //get data esk
            $data_esk = EskLists::find()->where(['id' => $esk_id])->andWhere('status != "published"')->one();
            if (empty($data_esk)) {
                return ['code' => 0, 'status' => 'Failed process request because eSK data with id ' . $esk_id . ' is not found!'];
            }

            //get data ack 
            $ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
            if ($nik_approver != $ack->ack_nik) {
                return ['code' => 0, 'status' => 'Failed process request because nik is not valid approval!'];
            }

            //get data acknowledge 
            $countPending = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'status' => 'pending'])->count();

            //kirim datanya ke fungsi workflow status
            if ($countPending <= 0 || empty($ack->next->ack_title)) {
                $data_update = Model::WorkFlowStatus("published", '', $data_esk->id);
                $flag_publish = true;
            } else {
                $data_update = Model::WorkFlowStatus("delivered", $ack->id, $data_esk->id);
                $data_esk->flag_ack_seq = $data_update['flag_approval_seq'];
                $flag_publish = false;
            }
            $data_esk->status = $data_update['status'];
            $data_esk->tracking = $data_update['tracking'];

            if ($data_esk->save()) {
                //update data approval statusnya
                $data_ack = EskAcknowledgeLists::findOne($ack->id);
                $data_ack->status = "acknowledge";
                $data_ack->ack_at = date("Y-m-d H:i:s");
                $data_ack->save();

                //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
                $nik = $nik_approver;

                if ($flag_publish) {
                    if ($data_ack->ack_nik == $nik) {
                        $action = $data_ack->ack_title . " menerbitkan eSK untuk " . $data_esk->nik . "/" . $data_esk->nama . ".";
                    } else {
                        $action = $data_ack->ack_title . " menerbitkan eSK untuk " . $data_esk->nik . "/" . $data_esk->nama . ". (action by HCBP Account/Area)";
                    }
                    $published_by = $data_ack->ack_title;

                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Published eSK data with ID " . $data_esk->id . " by " . $published_by);

                    //submit posting career 
                    Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

                    //send mail to other ack
                    $subject = "[eSK] Published of eSK Number " . $data_esk->number_esk . "";
                    $to = Model::getOtherAck($data_esk->id, $ack->id);
                    $content = $this->renderPartial('mail-published-ack', ['data_esk' => $data_esk], true);
                    //Model::sendMailMultiple($to,$subject,$content);

                    $to = $data_esk->employee->email;
                    $content = $this->renderPartial('mail-published', ['data_esk' => $data_esk], true);
                    Model::sendNotifMoana($to, 'My Assignment • New Update', ucwords(strtolower($data_esk->about_esk)));
                    //Model::sendMailOne($to,$subject,$content);

                    //cek band
                    $databp = explode(".", $data_esk->new_bp);
                    if ($databp[0] == 5 || $databp[0] == 6) {
                        $to = Model::getDirectionMail($data_esk->new_directorate);
                    } else {
                        $to = Model::getHCBPOfficers($data_esk->authority, $data_esk->new_directorate);
                    }

                    //send mail to hcbp area
                    $content = $this->renderPartial('mail-published-ack', ['data_esk' => $data_esk], true);
                    //Model::sendMailMultiple($to,$subject,$content);
                } else {
                    if ($data_ack->ack_nik == $nik) {
                        $action = $data_ack->ack_title . " mengakui eSK Karyawan.";
                    } else {
                        $action = $data_ack->ack_title . " mengakui eSK Karyawan. (action by HCBP Account/Area)";
                    }

                    //logging data
                    Model::saveLog(Yii::$app->user->identity->username, "Acknowledge eSK data with ID " . $data_esk->id . " by " . $data_ack->ack_title);

                    //send mail
                    $subject = "[eSK] Delivered of eSK Number " . $data_esk->number_esk . "";
                    $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
                    $content = $this->renderPartial('mail-delivered', ['esk' => $data_esk, 'head' => $data_ack->ack_name], true);
                    //Model::sendMailOne($data_ack->ack_mail,$subject,$content);
                }

                Model::setWorkFlow($ack->id, $action, "cronack");

                $label = ($flag_publish == true) ? "publish" : "acknowledge";
                return ['code' => 1, 'status' => 'Success ' . $label . ' request'];
            } else {
                //set failed count
                $error = implode(",", $data_esk->getErrorSummary(true));
                if ($flag_publish) {
                    //logging data
                    Model::saveLog($nik_approver, "Failed published eSK data for ID " . $data_esk->id . " because " . $error);
                } else {
                    //logging data
                    Model::saveLog($nik_approver, "Failed acknowledge eSK data for ID " . $data_esk->id . " because " . $error);
                }

                return ['code' => 0, 'status' => 'Failed process request'];
            }
        } catch (\ErrorException $e) {
            throw new HttpException('500', $e);
        }
    }

    public function actionPublishData()
    {
        $data = Yii::$app->request->post();
        try {
            $esk_id = $data['workflow_id'];
            $nik_approver = empty($data['nik_approver']) ? null : $data['nik_approver'];

            //get data esk
            $data_esk = EskLists::find()->where(['id' => $esk_id])->andWhere('status != "published"')->one();
            if (empty($data_esk)) {
                return ['code' => 0, 'status' => 'Failed process request because eSK data with id ' . $esk_id . ' is not found!'];
            }

            //get data ack 
            $ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
            if ($nik_approver != $ack->ack_nik) {
                return ['code' => 0, 'status' => 'Failed process request because nik is not valid approval!'];
            }

            //kirim datanya ke fungsi workflow status
            $data_update = Model::WorkFlowStatus("published", '', $data_esk->id);
            $data_esk->status = $data_update['status'];
            $data_esk->tracking = $data_update['tracking'];

            //data ack 
            $data_ack = EskAcknowledgeLists::find()->where(['id_esk' => $data_esk->id, 'sequence' => $data_esk->flag_ack_seq])->one();
            $max_sequence = EskAcknowledgeLists::find()->select(['max(sequence) as sequence'])->where(['id_esk' => $data_esk->id])->one();

            //flag ack 
            $flag_ack = EskFlagData::find()->one()->flag_ack;

            if ($data_esk->save()) {
                //get nik user 
                $nik = $nik_approver;

                //update ack jika tidak kosong
                if (!empty($data_ack) && $flag_ack == 1) {
                    //update data approval statusnya
                    $data_ack = EskAcknowledgeLists::findOne($data_ack->id);
                    $data_ack->status = "acknowledge";
                    $data_ack->ack_at = date("Y-m-d H:i:s");
                    $data_ack->save();

                    //save workflow esk and check apakah dilakukan oleh approval sendiri atau bukan
                    if ($data_ack->ack_nik == $nik) {
                        $action = $data_ack->ack_title . " menerbitkan eSK untuk " . $data_esk->nik . "/" . $data_esk->nama . ".";
                    } else {
                        $action = $data_ack->ack_title . " menerbitkan eSK untuk " . $data_esk->nik . "/" . $data_esk->nama . ". (action by HCBP Account/Area)";
                    }
                    $published_by = $data_ack->ack_title;

                    //cek apakah last sequence
                    if ($data_ack->sequence != $max_sequence->sequence) {
                        //update all status ack if not last last sequence
                        $update_all_ack = EskAcknowledgeLists::updateAll(['status' => 'Skipped for Acknowledge Action'], 'id != ' . $data_ack->id . ' and id_esk = ' . $data_esk->id);

                        //send mail to other ack
                        $subject = "[eSK] Published of eSK Number " . $data_esk->number_esk . "";
                        $to = Model::getOtherAck($data_esk->id, $data_ack->id);
                        $content = $this->renderPartial('mail-published-ack', ['data_esk' => $data_esk], true);
                        //Model::sendMailMultiple($to,$subject,$content);
                    }
                } else {
                    if ($data_esk->vp_nik == $nik) {
                        $action = $data_esk->vP->title . " menerbitkan eSK untuk " . $data_esk->nik . "/" . $data_esk->nama . ".";
                    } else {
                        $action = $data_esk->vP->title . " menerbitkan eSK untuk " . $data_esk->nik . "/" . $data_esk->nama . ". (action by HCBP Account/Area)";
                    }
                    $published_by = $data_esk->vP->title;
                }
                Model::setWorkFlow($data_esk->id, $action, "cronpublish");

                //logging data
                Model::saveLog($nik_approver, "Published eSK data with ID " . $data_esk->id . " by " . $published_by);

                //submit posting career 
                Helper::postingCareer($data_esk->id, $data_esk->nik, $data_esk->old_title, $data_esk->new_title, $data_esk->effective_esk_date, $data_esk->tipe);

                //send mail to employee
                $subject = "[eSK] Published of eSK Number " . $data_esk->number_esk . "";
                $to = $data_esk->employee->email;
                $content = $this->renderPartial('mail-published', ['data_esk' => $data_esk], true);
                Model::sendNotifMoana($to, 'My Assignment • New Update', ucwords(strtolower($data_esk->about_esk)));
                //Model::sendMailOne($to,$subject,$content);

                //cek band
                $databp = explode(".", $data_esk->new_bp);
                if ($databp[0] == 5 || $databp[0] == 6) {
                    $to = Model::getDirectionMail($data_esk->new_directorate);
                } else {
                    $to = Model::getHCBPOfficers($data_esk->authority, $data_esk->new_directorate);
                }

                //send mail to hcbp area
                $subject = "[eSK] Published of eSK Number " . $data_esk->number_esk . "";
                $content = $this->renderPartial('mail-published-ack', ['data_esk' => $data_esk], true);
                //Model::sendMailMultiple($to,$subject,$content);

                return ['code' => 1, 'status' => 'Success publish request'];
            } else {
                //logging data
                $error = implode(",", $data_esk->getErrorSummary(true));
                Model::saveLog($nik_approver, "Failed published eSK data for ID " . $data_esk->id . " because " . $error);

                return ['code' => 0, 'status' => 'Failed process request'];
            }
        } catch (\ErrorException $e) {
            throw new HttpException('500', $e);
        }
    }

    public function actionRequestEskLists()
    {
        $nik = Yii::$app->request->post('employee_number');

        //validate header
        $headerData = Yii::$app->request->headers;
        if ($headerData->has('xusernik')) {
            $nik_header = $headerData->get('xusernik');
            if (strcmp($nik, $nik_header) !== 0) {
                $result['code'] = 0;
                $result['description'] = "You are not authorized to process this request";

                return $result;
                exit();
            }
        } else {
            $result['code'] = 0;
            $result['description'] = "Header of xusernik not found, please check again your configuration";

            return $result;
            exit();
        }

        $query = new Query;
        $query
            ->select([
                'esk_lists.id as transaction_id', 'esk_lists.tia_workflow_id workflow_id', 'esk_lists.number_esk',
                'esk_lists.about_esk', 'esk_lists.tipe type', 'esk_lists.nik', 'esk_lists.nama name', 'esk_lists.old_position',
                'esk_lists.new_position', 'esk_lists.old_organization', 'esk_lists.new_organization',
                'esk_lists.old_section', 'esk_lists.new_section', 'esk_lists.old_department', 'esk_lists.new_department',
                'esk_lists.old_division', 'esk_lists.new_division', 'esk_lists.old_bgroup', 'esk_lists.new_bgroup',
                'esk_lists.old_egroup', 'esk_lists.new_egroup', 'esk_lists.old_directorate', 'esk_lists.new_directorate',
                'esk_lists.old_area', 'esk_lists.new_area', 'esk_lists.old_bp', 'esk_lists.new_bp',
                'esk_lists.old_bi', 'esk_lists.new_bi', 'esk_lists.old_kota', 'esk_lists.new_kota',
                'esk_lists.created_at', 'esk_lists.updated_at'
            ])
            ->from('esk_lists')
            ->where(['esk_lists.nik' => $nik])
            ->andWhere('esk_lists.status = "delivered"')
            ->orderBy('esk_lists.created_at desc');
        $command = $query->createCommand();
        $model = $command->queryAll();

        if (!empty($model)) {
            return $model;
        } else {
            return (object) array();
        }
    }

    public function actionGetRequestEskLists()
    {
        $id = Yii::$app->request->post('transaction_id');

        $query = new Query;
        $query
            ->select([
                'esk_lists.id as transaction_id', 'esk_lists.tia_workflow_id workflow_id', 'esk_lists.number_esk',
                'esk_lists.about_esk', 'esk_lists.tipe type', 'esk_lists.nik', 'esk_lists.nama name', 'esk_lists.old_position',
                'esk_lists.new_position', 'esk_lists.old_organization', 'esk_lists.new_organization',
                'esk_lists.old_section', 'esk_lists.new_section', 'esk_lists.old_department', 'esk_lists.new_department',
                'esk_lists.old_division', 'esk_lists.new_division', 'esk_lists.old_bgroup', 'esk_lists.new_bgroup',
                'esk_lists.old_egroup', 'esk_lists.new_egroup', 'esk_lists.old_directorate', 'esk_lists.new_directorate',
                'esk_lists.old_area', 'esk_lists.new_area', 'esk_lists.old_bp', 'esk_lists.new_bp',
                'esk_lists.old_bi', 'esk_lists.new_bi', 'esk_lists.old_kota', 'esk_lists.new_kota',
                'esk_lists.created_at', 'esk_lists.updated_at'
            ])
            ->from('esk_lists')
            ->where(['esk_lists.id' => $id])
            ->andWhere('esk_lists.status = "delivered"')
            ->orderBy('esk_lists.created_at desc');
        $command = $query->createCommand();
        $model = $command->queryAll();

        if (!empty($model)) {
            return $model;
        } else {
            return (object) array();
        }
    }

    public function actionApprovalEskLists()
    {
        $data = Yii::$app->request->post();

        //validate header
        $headerData = Yii::$app->request->headers;
        if ($headerData->has('xusernik')) {
            $nik_header = $headerData->get('xusernik');
            if (strcmp($data['supervisor_nik'], $nik_header) !== 0) {
                $result['code'] = 0;
                $result['description'] = "You are not authorized to process this request";

                return $result;
                exit();
            }
        } else {
            $result['code'] = 0;
            $result['description'] = "Header of xusernik not found, please check again your configuration";

            return $result;
            exit();
        }

        $query = new Query;
        $query
            ->select([
                'esk_lists.id as transaction_id', 'esk_lists.tia_workflow_id workflow_id', 'esk_lists.number_esk',
                'esk_lists.about_esk', 'esk_lists.tipe type', 'esk_lists.nik', 'esk_lists.nama name', 'esk_lists.old_position',
                'esk_lists.new_position', 'esk_lists.old_organization', 'esk_lists.new_organization',
                'esk_lists.old_section', 'esk_lists.new_section', 'esk_lists.old_department', 'esk_lists.new_department',
                'esk_lists.old_division', 'esk_lists.new_division', 'esk_lists.old_bgroup', 'esk_lists.new_bgroup',
                'esk_lists.old_egroup', 'esk_lists.new_egroup', 'esk_lists.old_directorate', 'esk_lists.new_directorate',
                'esk_lists.old_area', 'esk_lists.new_area', 'esk_lists.old_bp', 'esk_lists.new_bp',
                'esk_lists.old_bi', 'esk_lists.new_bi', 'esk_lists.old_kota', 'esk_lists.new_kota',
                'esk_lists.created_at', 'esk_lists.updated_at'
            ])
            ->from('esk_lists')
            ->join('join', 'esk_acknowledge_lists', 'esk_acknowledge_lists.id_esk = esk_lists.id')
            ->where(['esk_acknowledge_lists.ack_nik' => $data['supervisor_nik']])
            ->andWhere('esk_lists.status = "delivered" AND esk_lists.flag_ack_seq = esk_acknowledge_lists.sequence')
            ->orderBy('esk_lists.created_at desc');
        $command = $query->createCommand();
        $model = $command->queryAll();

        if (!empty($model)) {
            return $model;
        } else {
            return (object) array();
        }
    }
    //--- approval time card end --//


    //=== API get published career by month ===//
    public function actionGetCareer($effectiveDate = null)
    {
        if (!empty($effectiveDate)) {
            //validate format
            if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])$/", $effectiveDate)) {
                return $this->errorMessageMeta('Effective date format wrong (format YYYY-MM)', '406', 'not acceptable');
            }
        }

        $currDate = empty($effectiveDate) ? date("Y-m") : $effectiveDate;

        //get data esk 
        $data = [];
        $getData = EskLists::find()->where('effective_esk_date LIKE "' . $currDate . '-%" and status = "published"')
            ->andWhere('(
            tipe LIKE "promosi%" || tipe LIKE "Promosi%" 
            || tipe LIKE "rotasi%" || tipe LIKE "Rotasi%" 
            || tipe LIKE "Pengangkatan%"
            || tipe = "Penetapan Karyawan Memasuki MPP"
            )')
            ->orderBy('nama ASC')->all();
        if (!empty($getData)) {
            foreach ($getData as $row) {
                $tmp = [];
                $tmp['nik'] = $row->nik;
                $tmp['name'] = $row->nama;
                $tmp['tipe'] = $row->tipe;
                $tmp['old_position'] = $row->old_position;
                $tmp['new_position'] = $row->new_position;
                $tmp['old_organization'] = $row->old_organization;
                $tmp['new_organization'] = $row->new_organization;
                $tmp['old_section'] = $row->old_section;
                $tmp['new_section'] = $row->new_section;
                $tmp['old_department'] = $row->old_department;
                $tmp['new_department'] = $row->new_department;
                $tmp['old_division'] = $row->old_division;
                $tmp['new_division'] = $row->new_division;
                $tmp['old_group'] = $row->old_bgroup;
                $tmp['new_group'] = $row->new_bgroup;
                $tmp['old_executive_group'] = $row->old_egroup;
                $tmp['new_executive_group'] = $row->new_egroup;
                $tmp['old_directorate'] = $row->old_directorate;
                $tmp['new_directorate'] = $row->new_directorate;
                $tmp['effective_date'] = $row->effective_esk_date;
                $data[] = $tmp;
            }

            return $this->successMessageMeta(true, count($data), $data);
        } else {
            return $this->errorMessageMeta('Data career is empty.', '404', 'attribute not found');
        }
    }

    public function actionGetOrganization()
    {
        $getData = Employee::find()->where('status = "AKTIF"')
            ->groupBy('organization')
            ->all();
        if (!empty($getData)) {
            $data = [];
            foreach ($getData as $row) {
                array_push($data, $row->organization);
            }
            return $this->successMessageMeta(true, count($data), $data);
        } else {
            return $this->errorMessageMeta('Data organization is empty.', '404', 'attribute not found');
        }
    }

    public function actionGetCareerMovement()
    {
        $headerData = Yii::$app->request->headers;
        $nik = null;
        if (!$headerData->has('xusernik')) {
            return $this->errorMessageMeta('You are not authorized to process this request', '401', 'unauthorized');
        } else {
            $nik = $headerData->get('xusernik');
        }

        //get data esk 
        $cutOffDate = date("Y-m-01", strtotime("-6 month"));
        $getData = EskLists::find()->where(['nik' => $nik])
            ->andWhere('effective_esk_date >= "' . $cutOffDate . '" and status = "published"')
            ->andWhere('(
            tipe LIKE "promosi%" || tipe LIKE "Promosi%" 
            || tipe LIKE "rotasi%" || tipe LIKE "Rotasi%"
            )')
            ->orderBy('effective_esk_date DESC')
            ->one();

        if (!empty($getData)) {
            //validation category 
            if (
                strpos(strtolower($getData->tipe), "promosi") !== false ||
                strpos(strtolower($getData->tipe), "promotion") !== false
            ) {
                $category = "promotion";
            }else{
                $category = 'rotation';
            }

            $data = [];
            $data['career_id'] = $getData->id;
            $data['nik'] = $getData->nik;
            $data['old_title'] = $getData->old_title;
            $data['new_title'] = $getData->new_title;
            $data['category'] = $category;
            $data['effective_date'] = $getData->effective_esk_date;

            return $this->successMessageMeta(false, 0, $data);
        } else {
            return $this->errorMessageMeta('Data career movement is empty.', '404', 'attribute not found');
        }
    }
}
