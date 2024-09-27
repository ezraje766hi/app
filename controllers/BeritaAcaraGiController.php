<?php

namespace esk\controllers;

use common\models\City;
use esk\components\Helper;
use esk\models\EskBeritaAcaraDetailOther;
use esk\models\EskTemplateDetail;
use esk\models\Employee;
use esk\models\EskBeritaAcaraDetailOtherTemp;
use esk\models\EskCodeParam;
use esk\models\EskContent;
use esk\models\EskListsTemp;
use esk\models\Model;
use esk\models\Position;
use Yii;
use yii\web\Controller;

class BeritaAcaraGiController extends Controller
{
    protected $templateDetail;
    protected $templateMaster;
    protected $salary;
    protected $sequences;
    protected $countSection;
    protected $employee;
    protected $eskContent;
    protected $eskContentSection;
    protected $templateContentIds;
    protected $templateSectionIds;
    protected $templateMasterSection;
    protected $eskParentContentCount;
    protected $templateParentCount;
    protected $beritaAcara;

    function init(){
        $this->countSection             = [];
        $this->eskParentContentCount    = [];
        $this->templateParentCount      = [];
        $this->beritaAcara              = [];
    }
    
    public function actionPdfVerifikasi($id)
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $beginTime = microtime(true);

        $baOther        = EskBeritaAcaraDetailOther::find()->with('beritaAcaras')->where(['id_master' => $id])->all();
        $result         = [];
        $templateMaster = [];
        $bibp           = [];
        $niks           = [];
        $ids            = [];
        foreach ($baOther as $v) {
            $databp = explode(".", $v['new_bp']);
            if(!empty($v['new_bi'])){
                $bi = $v['new_bi'];
            }else{
                $bi = $v['old_bi'];
            }
            $ids[] = $v['id'];

            // begin template master
            $key = $v['tipe'] . '~' . $v['code_template'] . '~' . $databp[0] . '~' . $v['old_area'] . '~' . $v['new_area'] . '~' . $v['old_directorate'];
            $key = str_replace([' ', "'"], ["_", "_"], $key);
            $templateMaster[$key] = [
                'type'              => $v['tipe'],
                'code'              => $v['code_template'],
                'bp'                => $databp[0],
                'old_area'          => $v['old_area'],
                'new_area'          => $v['new_area'],
                'old_directorate'   => $v['old_directorate']
            ];

            $bibp[$bi][$v['new_bp']] = $bi.'~'.$v['new_bp'];

            $niks[] = $v['nik'];
            $this->beritaAcara[$v['id_master']] = $v['beritaAcaras'];
        }

        $this->templateMasterList($templateMaster);
        $this->bibpList($bibp);
        $this->employees($niks);

        foreach ($baOther as $v) {
            $timeStart = microtime(true);
            $id     = $v['id'];
            $databp = explode(".", $v['new_bp']);
            if(!empty($v['new_bi'])){
                $bi = $v['new_bi'];
            }else{
                $bi = $v['old_bi'];
            }

            $model   = EskListsTemp::find()->where(['id_ba_detail' => $id])->one();
            if(!$model)
                $model = new EskListsTemp();

            $databp = explode(".",$v['new_bp']);
            $dataoldbp = explode(".",$v['old_bp']);
            //explode data BP 
            $key = $v['tipe'] . '~' . $v['code_template'] . '~' . $databp[0] . '~' . $v['old_area'] . '~' . $v['new_area'] . '~' . $v['old_directorate'];
            $key = str_replace([' ', "'"], ["_", "_"], $key);
            if(array_key_exists($key, $this->templateMaster)){
                $data_esk_master = $this->templateMaster[$key];

                $flag_gaji = 1;
                $flag_uang_pisah = null;
                $flag_ganti_rumah = null;
                $flag_ganti_cuti = null;
                $flag_homebase = null;
                $flag_insentif = 1;
                $flag_ket_kerja = 1;
                $salary = $this->salary[$bi][$v['new_bp']];
            
                //content id, nik, flag_kisel, last_payroll, flag_preview, flag_phk
                if($v['positionNew']->structural == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                    $flag_tunjangan_jabatan = 1;
                    $strukctural_data = $v['positionNew']->structural;
                    $functional_data = null;
                }elseif($v['positionNew']->functional == "Y"){
                    $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                    $flag_tunjangan_jabatan = 1;
                    $strukctural_data = null;
                    $functional_data = $v['positionNew']->functional;
                }elseif(strpos($v['new_title'], 'Senior Staff') !== false || ($v['band'] == 1 && $v['level_band'] <= 1) || strpos($v['new_title'], 'Senior Advisor Associate') !== false || strpos($v['new_title'], 'Advisor Associate') !== false || strpos($v['new_title'], 'Telkomsel Next Gen Associate') !== false || strpos($v['new_title'], 'Senior Associate') !== false) { //sini buru
                    $flag_tunjangan_jabatan = 0;
                    $strukctural_data = null;
                    $functional_data = null;
                }else{
                    if($v['employee']->structural == "Y"){
                        $flag_tunjangan_jabatan = $salary['tunjangan_jabatan'];
                        $flag_tunjangan_jabatan = 1;
                        $strukctural_data = $v['employee']->structural;
                        $functional_data = null;
                    }elseif($v['employee']->functional == "Y"){
                        $flag_tunjangan_jabatan = $salary['tunjangan_fungsional'];
                        $flag_tunjangan_jabatan = 1;
                        $strukctural_data = null;
                        $functional_data = $v['employee']->functional;
                    }else{
                        $flag_tunjangan_jabatan = 0;
                        $strukctural_data = null;
                        $functional_data = null;
                    }
                }
                
                $content_sk = $this->generateEsk($data_esk_master['id'],$v['nik'],$v['flag_kisel'],$v['last_payroll'],"",$flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja, null, $flag_tunjangan_jabatan);
                $result[$v['id']] = $v['nik'];

                $today = date("Y-m-d");
    
                $selisih = strtotime($v['effective_date']) - strtotime($today);
                $days = floor($selisih / (60*60*24));
                if($days < 0 ){
                    $flag_backdate = 1;
                }else{
                    $flag_backdate = 0;
                }
                $new_effective_date = $v['effective_date'];
                $new_flag_backdate = $flag_backdate;
                

                //replace data content sknya lepar juga tgl_berlaku_sk
                $replace_sk = $this->replaceBA(
                    $id,
                    $data_esk_master['id'],
                    $new_effective_date,
                    $content_sk,
                    $salary,
                    $data_esk_master['decree_title'],
                    $data_esk_master['authority'],
                    $v['periode'],
                    $v['nota_dinas'],
                    "", // $v['manual_content_1'],
                    "", // $v['manual_content_2'],
                    "", // $v['manual_content_3'],
                    $v['keterangan_ba_1'],
                    $v['keterangan_ba_2'],
                    $v['keterangan_ba_3'],
                    $v['keputusan_direksi_1'],
                    $v['keputusan_direksi_2'],
                    $v['keputusan_direksi_3'],
                    $v['nama_penyakit'],
                    $v['nominal_insentif'],
                    $v
                );

                //get atasan
                if(strpos($v['tipe'],"Position Applied from Exchange") !== false){
                    $data_posisi_new = Position::findOne($v['new_position_id']);
                    if(!empty($data_posisi_new)){
                        //get data employee dengan posisition_id
                        $data_employee_new = Employee::find()->where(['position_id' => $v['new_position_id']])->one();
                        if(!empty($data_employee_new)){
                            $atasan_array = Model::getHead($data_employee_new->nik);
                            $atasan = implode(";",$atasan_array);
                        }else{
                            $atasan_array = Model::getHead($v['nik']);
                            $atasan = implode(";",$atasan_array);
                        }
                    }else{
                        $atasan_array = Model::getHead($v['nik']);
                        $atasan = implode(";",$atasan_array);
                    }
                }else{
                    $atasan_array = Model::getHead($v['nik']);
                    $atasan = implode(";",$atasan_array);
                }

                if(!empty($atasan_array)){
                    foreach($atasan_array as $emp){
                        $data_emp = Employee::find()->where(['nik' => $emp])->one();
                        if((strpos(strtolower($data_emp->job_category), "vice president") !== false && ($dataoldbp[0] != 5 || $dataoldbp[0] != 6)) || strpos(strtolower($data_emp->job_category), "director") !== false){
                            if($dataoldbp[0] == 5 || $dataoldbp[0] == 6){
                                //get data gm hcm HO
                                $vp_nik = Model::getGMhcbp($data_esk_master['authority']);
                            }else{
                                $vp_nik = $emp;
                            }
                            break;
                        }else{
                            $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
                        }
                    }
                }else{
                    $data_emp = Employee::find()->where(['nik' => $v['nik']])->one();
                    $vp_nik = (empty($data_emp)) ? "" : $data_emp->nik_atasan;
                }

                //get data employee 
                $emp_ba = Employee::find()->where(['nik' => $v['nik']])->one();

                //set dan save data esk
                // $model->attributes = $v['attributes'];
                $model->id_ba_detail = $v['id'];
                $model->nama = $v['nama'];
                $model->nik = $v['nik'];
                $model->nomor_ba = $v['beritaAcaras']->no;
                $model->ba_date = $v['beritaAcaras']->ba_date;
                $model->about_esk = $data_esk_master['about'];
                $model->no_esk = $data_esk_master['number_esk']; //dari esk template authority
                $model->content_esk = $replace_sk;
                $model->effective_esk_date = $new_effective_date;
                $model->flag_backdate = $new_flag_backdate;
                $model->head_nik = $atasan;
                $model->vp_nik = $vp_nik;
                $model->status = "generated"; //ubah jika sudah bisa approval jadi 'generate' pertama kalinya
                $model->tracking = "Generated"; //ubah sesuai dengan approvalnya nanti
                $model->created_by = Yii::$app->user->identity->id;
                $model->id_template_master = $data_esk_master['id'];
                $model->batch_name = "";
                $model->id_batch = "";
                $model->old_position = $v['old_title']; //(!empty($v['positionOld'])) ? $v['positionOld']->nama : $v['old_title'];
                $model->new_position = $v['new_title']; //(!empty($v['positionNew'])) ? $v['positionNew']->nama : $v['new_title'];
                $model->mutasi = "";
                $model->eva = "";
                $model->dpe_ba = $v['dpe'];
                $model->dpe = empty($v['dpe']) || $v['dpe'] == '0000-00-00' ? 0 : strtotime($v['dpe']);
                $model->authority = $data_esk_master['authority']; //dari esk template authority
                $model->structural = $strukctural_data;
                $model->functional = $functional_data;
                $model->gaji_dasar = $salary['gaji_dasar'];
                $model->tunjangan_biaya_hidup = $salary['tunjangan_biaya_hidup'];
                $model->tunjangan_jabatan = $salary['tunjangan_jabatan'];
                $model->tunjangan_fungsional = $salary['tunjangan_fungsional'];
                $model->tunjangan_rekomposisi = $salary['tunjangan_rekomposisi'];
                $model->level_tbh = $v['new_bi'];
                $model->level_tr = $v['new_bi'];
                if((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false) && $model->level_band <= 4){
                    // PJS
                    $model->decree_nama = "Indrawan Ditapradana";
                    $model->decree_nik = "7310004";
                    $model->decree_title = "Director Human Capital Management";
                    $model->represented_title = "Direksi Perseroan";
                } elseif((strpos(strtolower($model->tipe), "pejabat sementara") !== false || strpos(strtolower($model->tipe), "mutasi aps") !== false || strpos(strtolower($model->tipe), "sakit berkepanjangan") !== false) && $model->level_band >= 5){
                    $model->decree_nama = "Nugroho";
                    $model->decree_nik = "7610001";
                    $model->decree_title = "President Director";
                    $model->represented_title = "Direksi Perseroan";
                } else {
                    $model->decree_nama = $data_esk_master['decree_nama'];
                    $model->decree_nik = $data_esk_master['decree_nik'];
                    $model->decree_title = $data_esk_master['decree_title'];
                    $model->represented_title = $data_esk_master['represented_title'];
                }
                $model->is_represented = $data_esk_master['is_represented'];
                $model->city_esk = $data_esk_master['city_esk'];
                $model->file_name = $data_esk_master['file_name'];
                $model->nota_dinas = $v['nota_dinas'];
                $model->periode = $v['periode'];
                $model->nama_penyakit = $v['nama_penyakit'];
                $model->nominal_insentif = $v['nominal_insentif'];
                $model->manual_content_1 = "";
                $model->manual_content_2 = "";
                $model->manual_content_3 = "";
                $model->keterangan_ba_1 = $v['keterangan_ba_1'];
                $model->keterangan_ba_2 = $v['keterangan_ba_2'];
                $model->keterangan_ba_3 = $v['keterangan_ba_3'];
                $model->keputusan_direksi_1 = $v['keputusan_direksi_1'];
                $model->keputusan_direksi_2 = $v['keputusan_direksi_2'];
                $model->keputusan_direksi_3 = $v['keputusan_direksi_3'];
                $model->alamat = (!empty($emp_ba)) ? $emp_ba->alamat : "";

                //set data creator
                if((!empty(Yii::$app->user->identity->nik) && !empty(Yii::$app->user->identity->employee))){
                    if(Yii::$app->user->can('sysadmin')){
                        $model->created_authority = "HEAD OFFICE";
                        $model->head_created = Employee::find()
                        ->join('JOIN','user','user.nik = employee.nik')
                        ->join('JOIN','auth_assignment','auth_assignment.user_id = user.id')
                        ->where(['auth_assignment.item_name' => 'hc_staffing', 'employee.job_category' => 'manager'])->one()->nik;
                    }else{
                        $model->created_authority =Yii::$app->user->identity->employee->area;
                        $model->head_created = Yii::$app->user->identity->employee->nik_atasan;
                    }
                }else{
                    $model->created_authority = "HEAD OFFICE";
                    $model->head_created = Employee::find()
                    ->join('JOIN','user','user.nik = employee.nik')
                    ->join('JOIN','auth_assignment','auth_assignment.user_id = user.id')
                    ->where(['auth_assignment.item_name' => 'hc_staffing', 'employee.job_category' => 'manager'])->one()->nik;
                }
                $model->flag_ack_seq = 1;
                if($model->save()){
                    $result[$v['id']] = "generate preview BA Temp " . $v['nik'] . " berhasil";
                }else{
                    $error = implode(",",$model->getErrorSummary(true));
                    $result[$v['id']] = "generate preview BA Temp " . $v['nik'] . " error: " . $error;
                }
            } else {
                $result[$v['id']] = "data template master dari " . $v['nik'] . ' tidak ditemukan';
            }
            
            $timeEnd = microtime(true);
            $execTime = ($timeEnd - $timeStart)/60;
            $result[$v['id']] .= " (id : ".$v['id'].") => dalam waktu: {$execTime} mins<br/>";
        }
        
        echo '<pre>' . print_r($result, 1);
        $stopTime = microtime(true);
        $totalTime = ($stopTime - $$beginTime)/60;
        echo "total execution time: {$totalTime} mins"; 
    }

    function bibpList($list){
        $result = [];
        foreach ($list as $bi => $listBp) {
            foreach ($listBp as $bp) {
                $result[$bi][$bp] = Model::getSalaryData($bi, $bp);
            }
        }
        $this->salary = $result;
    }

    function templateMasterList($list){
        $result     = [];
        $masterIds  = [];
        foreach ($list as $k => $v) {
            $rslt = Model::checkTemplateMaster(
                $v['type'],
                $v['code'],
                $v['bp'],
                $v['old_area'],
                $v['new_area'],
                $v['old_directorate']
            );
            $result[$k] = $rslt;
            if(count($rslt) > 0){
                $masterIds[] = $rslt['id'];
            }
        }
        $this->templateMaster = $result;
        if(count($masterIds) > 0){
            $this->templateDetailList($masterIds);
        }
    }

    function employees($niks)
    {
        $data =  Employee::find()->where(['in', 'nik', $niks])->select('nik, status_pernikahan, structural, functional, gender, tanggal_masuk, homebase, alamat')->all();
        foreach ($data as $v) {
            $this->employee[$v['nik']] = $v['status_pernikahan'];
        }
    }

    private function templateDetailList($masterIds){
        $result     = [];
        $sequences  = [];
        $contentIds = [];
        $sectionIds = [];
        $data = EskTemplateDetail::find()->where(['in', 'id_esk_master', $masterIds])->with('section', 'content')->orderBy("sequence ASC")->all();
        foreach ($data as $v) {
            $result[$v['id_esk_master']][] = $v;

            if($v['sequence'] == 1){
                $keySequence = $v['id_esk_master'] . '~' . $v['sequence'];
                $sequences[$keySequence] = $v;
            }

            $keySection = $v['id_esk_master'] . '~' . $v['id_section'];
            if(array_key_exists($keySection, $this->countSection))
                $this->countSection[$keySection]++;
            else
                $this->countSection[$keySection] = 1;
            $this->templateMasterSection[$keySection][$v['sequence']] = $v;
            ksort($this->templateMasterSection[$keySection], SORT_ASC);

            $contentIds[$v['id_content']] = $v['id_content'];
            $sectionIds[$v['id_section']] = $v['id_section'];
        }
        $this->templateDetail       = $result;
        $this->sequences            = $sequences;
        $this->templateContentIds   = $contentIds;
        $this->templateSectionIds   = $sectionIds;
        
        if(count($this->templateContentIds) > 0){
            if(count($this->templateSectionIds) > 0){
                $this->templateContentSection();
                $this->templateContentSectionParent();
            }
            $this->templateContentID();
        }
    }

    function templateContentSection()
    {
        $result = [];
        $data = EskContent::find()->where(['in', 'parent_id', $this->templateContentIds])->andWhere(['in', 'id_section', $this->templateSectionIds])->all();
        foreach ($data as $v) {
            $key = $v['parent_id'] . '~' . $v['id_section'];
            $result[$key] = $v;
        }
        $this->eskContentSection = $result;
    }
    
    function templateContentID()
    {
        $result     = [];
        $parents    = [];
        $data = EskContent::find()->where(['in', 'id', $this->templateContentIds])->all();
        foreach ($data as $v) {
            $result[$v['id']] = $v;
            $parents[$v['parent_id']] = $v['parent_id'];
        }
        $this->eskContent = $result;
        if(count($parents) > 0){
            $this->templateContentSubCount($parents);
        }
    }

    function templateContentSectionParent()
    {
        $data = EskContent::find()->where(['in', 'parent_id', $this->templateContentIds])->all();
        foreach ($data as $v) {
            $key = $v['parent_id'] . '~' . $v['id_section'];
            if(array_key_exists($v['parent_id'], $this->eskParentContentCount))
                $this->eskParentContentCount[$v['parent_id']]++;
            else
                $this->eskParentContentCount[$v['parent_id']] = 1;
        }
    }

    function templateContentSubCount($parents)
    {
        $data = EskContent::find()->select(['count(*) as jml', 'parent_id'])->where(['in', 'parent_id', $parents])->andWhere(['=', 'is_sub_content', '1'])->groupBy('parent_id')->createCommand()->queryAll();
        
        foreach ($data as $v) {
            $this->templateParentCount[$v['parent_id']] = $v['jml'];
        }
    }

    function numberSubContentList($parents){
        $data = EskTemplateDetail::find()->where(['in', 'parent_id', $parents])->select('parent_id, id_section, id')->all();
        $result = [];
        foreach ($data as $v) {
            $result[$v['parent_id']][$v['id_section']][$v['id']] = $v['id'];
        }
        $this->numberSubContent = $result;

    }

    public function generateEsk($id, $nik = null, $flag_kisel = null, $last_payroll = null, $preview = null, $flag_gaji = null, $flag_uang_pisah = null, $flag_ganti_rumah = null, $flag_ganti_cuti = null, $flag_homebase = null, $flag_insentif = null, $flag_ket_kerja = null, $uang_pisah_value = null, $flag_tunjangan_jabatan){
        $data_emp = $this->employee[$nik];
        $bulan_masuk = "0";
        $uang_pisah = (empty($uang_pisah_value)) ? "0" : $uang_pisah_value;
        $mutasi = "0";

        //validasi gaji
        if(!empty($last_payroll)){
            $bulan_gaji = date("d",strtotime($last_payroll));
            $last_day = date("t",strtotime($last_payroll));
            if($bulan_gaji == $last_day){
                $flag_bulan_gaji_full = "1";
            }else{
                $flag_bulan_gaji_full = "0";
            }
        }else{
            $flag_bulan_gaji_full = "0";
        }

        //validasi preview
        if(!empty($preview)){
            $bulan_masuk = "12";
            $uang_pisah = "1";
            $flag_bulan_gaji_full = "1";
            $mutasi = "1";
            $flag_gaji = 1;
            $flag_insentif = 1;
            $flag_ket_kerja = 1;
        }

        //inisialiasi data awal
        $details = $this->templateDetail[$id];
        $prevSection = $this->sequences[$id . '~1']; // EskTemplateDetail::find()->where(['id_esk_master' => $id, 'sequence' => 1])->select(['id_section'])->one()->id_section;
        $content = array();
        $numbering = 1;
        $oldNumbering = 1;
        $oldSequence = 99;
        $iteration = 1;
        $countDetail = count($details);
        
        //perulangan detail eSK template
        foreach($details as $detail){
            //check next content
            if($iteration == $countDetail){
                $next_content = $detail->content->description;
                $next_2_content = "";
                $is_next_sub = 0;
                $next_parent_content = 0;
                $prev_parent_content = 0;
            }else{
                $next_content = $detail->next->content->description;
                $next_2_content = (!empty($detail->nextSecond['description'])) ? $detail->nextSecond['description'] : "";
                $is_next_sub = $detail->next->content->is_sub_content;
                $next_parent_content = $detail->next->content->parent_id;
                if(!empty($detail->prev->content)){
                    $prev_parent_content = $detail->prev->content->parent_id;
                }else{
                    $prev_parent_content = 0;
                }                
            }
            $prev_content = $detail->prevSecond->content->description;

            //iteration numbering validation check is previous section
            if($prevSection != $detail->id_section || ($is_next_sub == 1 && $prev_parent_content !== $detail->content->parent_id)){
                $numbering = 1;
            }

            //check apakah is alphabet numbering
            if($detail->content->is_numbering_alphabet == 1){
                $oldNumbering = $numbering;
                $numbering =  Helper::ALPHABET_ORDER[$numbering-1];
            }

            //count section
            $row_count = $this->countSection[$detail->id_esk_master . '~' . $detail->id_section];

            //check apakah section pertama
            if(strpos($detail->section->title,"PERTAMA") !== false || strpos($detail->section->title,"KEDUA") !== false
            || strpos($detail->section->title,"KETIGA") !== false || strpos($detail->section->title,"KEEMPAT") !== false){
                if(strpos($detail->content->description,"|") !== false || strpos($detail->content->description,"{without_number}") !== false){
                    //check padding bottom
                    if($this->rowSpan($detail->id_esk_master,$detail->id_section,$detail->id_content,$detail->sequence) == 2){
                        $paddingBottom = "10px";
                    }else{
                        $paddingBottom = "3px";
                    }

                    //validasi data emp
                    if(!empty($data_emp)){
                        //check apakah lajang untuk daftar keluarga di template PHK
                        if(($data_emp->status_pernikahan == "S" && (strpos($detail->content->description,"{daftar_keluarga}") !== false || strpos($detail->content->description,"Daftar Keluarga") !== false)) || ($flag_tunjangan_jabatan <= 0 && strpos($detail->content->description, "{label_tunjangan_jabatan}") !== false) || ($flag_tunjangan_jabatan <= 0 && strpos($detail->content->description, "{tunjangan_jabatan_bss}") !== false)){
                            $pertamaContent = "";
                        }else{
							$pertamaContent = Model::setPertamaSection($detail->content->description);
						}
                    }else{
                        $pertamaContent = Model::setPertamaSection($detail->content->description);
                    }

                    array_push($content, "
                        <tr>
                            <td style='padding-bottom:".$paddingBottom.";' colspan='2' align='justify'>
                                <table width='100%' border='0' cellspacing='0' cellpadding='0' style='font-family:arial;font-size:9pt;'>
                                    ".$pertamaContent."
                                </table> 
                            </td>
                        </tr>
                    ");
                }else{
                    $dataContent = $this->setContetEsk($detail->id_esk_master,$detail->id_section,$detail->id_content, $detail->section->title, $detail->content->description,$next_content,$next_2_content,$prev_content,$is_next_sub,$row_count, $numbering,$detail->sequence, $flag_kisel, $uang_pisah, $flag_bulan_gaji_full, $bulan_masuk, $mutasi, $flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja);
                    array_push($content, $dataContent);
                }
            }
            //check apakah section memutuskan
            elseif(strpos($detail->section->title,"Memutuskan") !== false){
                array_push($content, "
                    <tr>
                        <td style='padding-bottom:15px;' colspan='4' align='center'><b>".$detail->content->description."</b></td>
                    </tr>
                ");
            }else{
                //check apakah sub content
                if($detail->content->is_sub_content == 1){
                    if($this->eskParentContentCount[$detail->content->id] <= 0 ){
                        //check padding bottom
                        if($this->rowSpan($detail->id_esk_master,$detail->id_section,$detail->id_content,$detail->sequence) == 2){
                            $paddingBottom = "10px";
                            $content_note = str_replace(";",".",$detail->content->description);
                        }else{
                            $paddingBottom = "3px";
                            $content_note = $detail->content->description;
                        }

                        //=== sub content tingkat pertama dan tidak memiliki sub content ===//
                        if( !$this->templateParentCount[$detail->content->id] ) { // !Model::isSubContentTwo($detail->content->id)){ // edy
                            $keySection = $detail->id_esk_master . '~' . $detail->id_section;
							$validasititik = $this->countSection[$keySection];
							
							if($numbering == $validasititik-1) {
								array_push($content, "
									<tr>
										<td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
										<td style='padding-bottom:".$paddingBottom.";' align='justify' colspan='2'>".$content_note.  ".</td>
									</tr>
								");
							} else {
								array_push($content, "
									<tr>
										<td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
										<td style='padding-bottom:".$paddingBottom.";' align='justify' colspan='2'>".$content_note. ";</td>
									</tr>
								");
							}
                        }else{
                            //=== sub content tingkat kedua ===//
                            array_push($content, "
                                <tr>
                                    <td style='padding-bottom:".$paddingBottom.";' align='justify' colspan='2'>
                                        <table width='100%' border='0' cellspacing='0' cellpadding='0' style='font-family:arial;font-size:9pt;'>
                                            <tr>
                                                <td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
                                                <td align='justify'>".$content_note."</td>
                                            </tr>
                                        </table> 
                                    </td>
                                </tr>
                            ");
                        }
                    }else{
                        //=== sub content tingkat pertama dan memiliki sub content===//
                        //define rowspan
                        $rowspan_sub = $this->eskParentContentCount[$detail->content->id]; // Model::isParentContent($detail->content->id) + 1;

                        if($oldSequence < $detail->sequence && $detail->prev->content->is_sub_content == 1){
                            // $sub_numbering = Model::getNumberSubContent($detail->content->parent_id, $detail->id, $detail->id_section);
                            $sub_numbering = count($this->numberSubContent[$detail->content->parent_id][$detail->id_section][$detail->id]);
                        }else{
                            $sub_numbering = $numbering;
                        }
                        $oldSequence = $detail->sequence;
                        array_push($content, "
                            <tr>
                                <td width='3%' style='vertical-align:top;' align='center' rowspan='".$rowspan_sub."'>".$sub_numbering.".</td>
                                <td style='padding-bottom:5px;' align='justify'>".$detail->content->description."</td>
                            </tr>
                        ");
                    }
                }else{
                    $dataContent = $this->setContetEsk($detail->id_esk_master,$detail->id_section,$detail->id_content, $detail->section->title, $detail->content->description,$next_content,$next_2_content,$prev_content,$is_next_sub,$row_count, $numbering,$detail->sequence,$flag_kisel,$uang_pisah, $flag_bulan_gaji_full, $bulan_masuk, $mutasi, $flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja);
                    array_push($content, $dataContent);
                }
            }
            
            //check lagi numbering alphabetnya kembalikan ke int
            if($detail->content->is_numbering_alphabet == 1){
                $numbering = $oldNumbering;
            }

            if(
                ($uang_pisah == 0 && ((strpos($detail->content->description,"{uang_pisah}") !== false && $flag_uang_pisah != 1) || (strpos($detail->content->description,"penggantian perumahan") !== false && $flag_ganti_rumah != 1 ))) || 
                ($bulan_masuk < 12 && strpos($detail->content->description,"hak atas sisa cuti") !== false && $flag_ganti_cuti != 1) || 
                ($mutasi == 0 && strpos($detail->content->description,"penggantian biaya pulang ke Home") !== false && $flag_homebase != 1) ||
                (strpos($detail->content->description,"gaji bulan") !== false && $flag_gaji != 1) ||
                (strpos($detail->content->description,"insentif semesteran") !== false && $flag_insentif != 1) || 
                (strpos($detail->content->description,"surat keterangan bekerja") !== false && $flag_ket_kerja != 1)
            ){
                $numbering = $numbering;
            }else{
                $numbering = $numbering + 1;
            }
            $iteration++;
            $prevSection = $detail->id_section;
        }

        $data = "
            <table width='100%' border='0' style='font-family:arial;font-size:9pt;'>
                ".implode("",$content)."
            </table>    
        ";

        return $data;
    }

    public function setContetEsk($id_esk_master,$id_section,$id_content, $section, $content, $next_content, $next_2_content, $prev_content, $is_next_sub, $row_count, $numbering,$sequence,$flag_kisel,$uang_pisah,$bulan_gaji, $bulan_masuk, $mutasi, $flag_gaji, $flag_uang_pisah, $flag_ganti_rumah, $flag_ganti_cuti, $flag_homebase, $flag_insentif, $flag_ket_kerja){
        //function rowspan jika content pertama
        if($this->rowSpan($id_esk_master,$id_section,$id_content,$sequence) == 1){
            //content 1 masuk dan cek lebih dari 1 tidak
            if($row_count <= 1 || strpos($next_content,"|") !== false || $is_next_sub == 1){
                //validation pagging-bottom
                if(strpos($next_content,"|") !== false || $is_next_sub == 1){
                    $paddingBottom = "3px";
                    $content_note = str_replace(";",".",$content);
                }else{
                    $paddingBottom = "10px";
                    $content_note = $content;
                }
				//nambah titik
				$issub 		 = $this->eskContent[$id_content]; // EskContent::findOne(['id' => $id_content]);
				if($section == "Menimbang" || $section == "Mengingat" || $section == "Memperhatikan" || $section == "KEEMPAT" || $section == "KELIMA" || $section == "KETIGA" || $section == "KEDUA" && strpos($content_note,":") == false) {
					if(strpos($content_note,":") !== false) {
						//sudah
						$dataContent = "
							<tr>
								<td width='20%' rowspan='".$row_count."' style='vertical-align:top;'>".$section."</td>
								<td width='2%' rowspan='".$row_count."' style='vertical-align:top;' align='center'>:</td>
								<td style='padding-bottom:".$paddingBottom.";' colspan='2' align='justify'>".$content_note."</td>
							</tr>
							";
					} else {
						$dataContent = "
						<tr>
							<td width='20%' rowspan='".$row_count."' style='vertical-align:top;'>".$section."</td>
							<td width='2%' rowspan='".$row_count."' style='vertical-align:top;' align='center'>:</td>
							<td style='padding-bottom:".$paddingBottom.";' colspan='2' align='justify'>".$content_note.".</td>
						</tr>
						";
					}
				} else{
					//sudah
					$dataContent = "
					<tr>
						<td width='20%' rowspan='".$row_count."' style='vertical-align:top;'>".$section."</td>
						<td width='2%' rowspan='".$row_count."' style='vertical-align:top;' align='center'>:</td>
						<td style='padding-bottom:".$paddingBottom.";' colspan='2' align='justify'>".$content_note."</td>
					</tr>
					";
				}
            }else{
                //isi content lebih dari 1 pakai numbering
				//ganti titik koma
                $dataContent = "
                <tr>
                    <td width='20%' rowspan='".$row_count."' style='vertical-align:top;'>".$section."</td>
                    <td width='2%' rowspan='".$row_count."' style='vertical-align:top;' align='center'>:</td>
                    <td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
                    <td style='padding-bottom:5px;' align='justify'>".$content.";</td>
                </tr>
                ";
            }
        }else{
            //check padding bottom
            if($this->rowSpan($id_esk_master,$id_section,$id_content,$sequence) == 2){
                $paddingBottom = "10px";
                $content_note = str_replace(";",".",$content);

                if($flag_kisel == 0 && strpos($prev_content,"anggota Koperasi Karyawan Telkomsel") !== false){
                    $numbering = (int) $numbering - 1;
                }else{
                    $numbering = $numbering;
                }
            }else{
                $paddingBottom = "3px";
                $content_note = $content;
            }

            if(
                ($flag_kisel == 0 && strpos($content,"anggota Koperasi Karyawan Telkomsel") !== false) || 
                ($uang_pisah == 0 && ((strpos($content,"{uang_pisah}") !== false && $flag_uang_pisah != 1) || (strpos($content,"penggantian perumahan") !== false && $flag_ganti_rumah != 1))) || 
                ($bulan_masuk < 12 && strpos($content,"hak atas sisa cuti") !== false && $flag_ganti_cuti != 1) ||
                ($mutasi == 0 && strpos($content,"penggantian biaya pulang ke Home") !== false && $flag_homebase != 1) || 
                (strpos($content,"gaji bulan") !== false && $flag_gaji != 1) ||
                (strpos($content,"insentif semesteran") !== false && $flag_insentif != 1) || 
                (strpos($content,"surat keterangan bekerja") !== false && $flag_ket_kerja != 1)
            ){
                $dataContent = "
                    <tr>
                        <td width='3%' style='vertical-align:top;' align='left'></td>
                        <td style='padding-bottom:0px;' align='justify'></td>
                    </tr>
                ";
            }else{
                if(
                    (
                        ($flag_kisel == 0 && strpos($next_content,"anggota Koperasi Karyawan Telkomsel") !== false) || 
                        ($uang_pisah == 0 && ((strpos($next_content,"{uang_pisah}") !== false && $flag_uang_pisah != 1) || (strpos($next_content,"penggantian perumahan") !== false && $flag_ganti_rumah != 1))) || 
                        ($bulan_masuk < 12 && strpos($next_content,"hak atas sisa cuti") !== false && $flag_ganti_cuti != 1) ||
                        ($mutasi == 0 && strpos($next_content,"penggantian biaya pulang ke Home") !== false && $flag_homebase != 1) || 
                        (strpos($next_content,"gaji bulan") !== false && $flag_gaji != 1) ||
                        (strpos($next_content,"insentif semesteran") !== false && $flag_insentif != 1) || 
                        (strpos($next_content,"surat keterangan bekerja") !== false && $flag_ket_kerja != 1)
                    ) && empty($next_2_content)){
                    //cek apakah content kisel itu last content
                    $paddingBottom = "10px";
                    $content_note = str_replace(";",".",$content_note);
                }else{
                    $paddingBottom = "3px";
                    $content_note = $content_note;
                }

                //check bulan gaji content
                if(strpos($content,"{last_payroll}") !== false){
                    //validasi tgl resign
                    if($bulan_gaji == 0 || $bulan_gaji == "0"){
                        $content_note = str_replace(";","",$content_note);
                        $content_note = $content_note." yang akan dibayarkan secara proporsional;";
                    }
                }
				
				//ganti titik
                $keySection = $id_esk_master . '~' . $id_section;
				$validasititik = $this->countSection[$keySection];
				$issub 		   = $this->eskContent[$id_content];
				if($section == "Menimbang" || $section == "Mengingat" || $section == "Memperhatikan" || $section == "KEEMPAT" || $section == "KELIMA" || $section == "KETIGA" || $section == "KEDUA") {
					if($issub->is_sub_content == 1)
						$validasititik = $validasititik - 1;
					
					if($numbering == $validasititik) {
						$dataContent = "
							<tr>
								<td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
								<td style='padding-bottom:".$paddingBottom.";' align='justify'>".$content_note.".</td>
							</tr>
						";
					} else {
						
						if($validasititik == 2)
							$validasititik = 'b';
						elseif($validasititik == 3)
							$validasititik = 'c';
						elseif($validasititik == 4)
							$validasititik = 'd';
						elseif($validasititik == 5)
							$validasititik = 'e';
							
						if($numbering == $validasititik && is_numeric($numbering) == false) {
							$dataContent = "
								<tr>
									<td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
									<td style='padding-bottom:".$paddingBottom.";' align='justify'>".$content_note.".</td>
								</tr>
							";
						} else {
							$dataContent = "
							<tr>
								<td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
								<td style='padding-bottom:".$paddingBottom.";' align='justify'>".$content_note.";</td>
							</tr>
							";
						}
					}
				} else {
					//sudah
					$dataContent = "
                    <tr>
                        <td width='3%' style='vertical-align:top;' align='left'>".$numbering.".</td>
                        <td style='padding-bottom:".$paddingBottom.";' align='justify'>".$content_note."</td>
                    </tr>
                ";
				}
            }
        }

        return $dataContent;
    }

    public function rowSpan($id_master, $id_section, $id_content, $sequence){
        $key = $id_master . '~' . $id_section;
        $sections = $this->templateMasterSection[$key];
        $len = count($sections)-1;
        $flag = 0;
        $i = 0;
        foreach($sections as $section){
            //check if first data
            if($i == 0 && ($section->id_content == $id_content) && ($section->sequence == $sequence)){
                $flag = 1;
                break;
            }
            //check if last data
            elseif($i == $len && ($section->id_content == $id_content)){
                $flag = 2;
                break;
            }else{
                $flag = 0;
            }
            $i++;
        }
        
        return $flag;
    }

    public function replaceBA($id_ba,$id_esk_master,$tgl_berlaku,$content_sk,$salary,$decree_title,$authority,$periode,$nota_dinas,$manual_content_1,$manual_content_2,$manual_content_3,$keterangan_ba_1,$keterangan_ba_2,$keterangan_ba_3,$keputusan_direksi_1,$keputusan_direksi_2,$keputusan_direksi_3,$nama_penyakit,$nominal_insentif, $temp = null){
        //get data dari table berita_acara_detail , template sk master
        if($temp == null){
            $ba_detail = null; // EskBeritaAcaraDetailOtherTemp::find()->where(['id' => $id_ba])->one(); // skip dulu utk GI
            $nomor_ba = "";
            $tanggal_ba = "";
        }else{
            $ba_detail = $temp;
            $nomor_ba = $this->beritaAcara[$temp['id_master']]->no;
            $tanggal_ba = Model::TanggalIndo($this->beritaAcara[$temp['id_master']]->ba_date);
        }

        //check apakah ba_detail null
        // skip utk GI
        // if(empty($ba_detail)){
        //     $ba_detail = GenerateEsk::find()->where(['id' => $id_ba])->one();
        //     $nomor_ba = $ba_detail->beritaAcaras->no;
        //     $tanggal_ba = Model::TanggalIndo($ba_detail->beritaAcaras->ba_date);
        //     echo 'ba detail kosong<br/>';
        // }

        $master = $this->templateMaster[$id_esk_master]; // EskTemplateMaster::find()->where(['id' => $id_esk_master])->one();
        $nik_emp = trim(preg_replace('/\s\s+/', ' ', $ba_detail->nik));
        $data_emp = $this->employee[$nik_emp]; // Employee::find()->where('nik = "'.$nik_emp.'"')->one();
        $data_employee = $data_emp;

        /*if(!empty($ba_detail->positionOld)){
            $posisi_lama = $ba_detail->positionOld->nama;
            //$posisi_baru = $ba_detail->positionNew->nama;
        }else{
            $posisi_lama = $ba_detail->old_title;
        }*/
        $posisi_lama = $ba_detail->old_title;
        $posisi_baru = $ba_detail->new_title;

        //validation data structural dan functional
        if($ba_detail->structural == "Y"){
            $tunjangan_jabatan = $salary['tunjangan_jabatan'];
            $label_tunjangan_jabatan = "Tunjangan Jabatan";
			$label_tunjangan_jabatan_new = "Tunjangan Struktural";
        }elseif($ba_detail->functional == "Y"){
            $tunjangan_jabatan = $salary['tunjangan_fungsional'];
            $label_tunjangan_jabatan = "Tunjangan Fungsional";
			$label_tunjangan_jabatan_new = "Tunjangan Fungsional";
        }else{
            if($data_employee->structural == "Y"){
                $tunjangan_jabatan = $salary['tunjangan_jabatan'];
                $label_tunjangan_jabatan = "Tunjangan Jabatan";
				$label_tunjangan_jabatan_new = "Tunjangan Struktural";
            }elseif($data_employee->functional == "Y"){
                $tunjangan_jabatan = $salary['tunjangan_fungsional'];
                $label_tunjangan_jabatan = "Tunjangan Fungsional";
				$label_tunjangan_jabatan_new = "Tunjangan Fungsional";
            }else{
                $tunjangan_jabatan = 0;
                $label_tunjangan_jabatan = "Tunjangan Jabatan";
				$label_tunjangan_jabatan_new = "Tunjangan Struktural";
            }
        }
        //set gender Sdr/Sdri
        if($data_employee->gender == "M"){
            $gender = "Sdr";
        }else{
            $gender = "Sdri";
        }

        //set uang pisah
        $lama_kerja = Model::DiffDate($data_employee->tanggal_masuk);
        $uang_pisah = Model::UangPisah($lama_kerja);

        // for GI
        $homebase = "";
        $status_pernikahan = "";
        $jumlah_anak = "";
        $keluarga = "";

        $tanggal_masuk = empty($data_employee->tanggal_masuk) ? "" : Model::TanggalIndo($data_employee->tanggal_masuk);

        //filter additional content
        $nota_dinas = (empty($nota_dinas)) ? $ba_detail->nota_dinas : $nota_dinas;
        $periode = (empty($periode)) ? $ba_detail->periode : $periode;
        $keterangan_ba_1 = (empty($keterangan_ba_1)) ? $ba_detail->keterangan_ba_1 : $keterangan_ba_1;
        $keterangan_ba_2 = (empty($keterangan_ba_2)) ? $ba_detail->keterangan_ba_2 : $keterangan_ba_2;
        $keterangan_ba_3 = (empty($keterangan_ba_3)) ? $ba_detail->keterangan_ba_3 : $keterangan_ba_3;
        $keputusan_direksi_1 = (empty($keputusan_direksi_1)) ? $ba_detail->keputusan_direksi_1 : $keputusan_direksi_1;
        $keputusan_direksi_2 = (empty($keputusan_direksi_2)) ? $ba_detail->keputusan_direksi_2 : $keputusan_direksi_2;
        $keputusan_direksi_3 = (empty($keputusan_direksi_3)) ? $ba_detail->keputusan_direksi_3 : $keputusan_direksi_3;
        $nama_penyakit = (empty($nama_penyakit)) ? $ba_detail->nama_penyakit : $nama_penyakit;
        if(!empty($nominal_insentif)){
            $data_insentif = Model::formatHargaInd($nominal_insentif);
        }else{
            $data_insentif = (empty($ba_detail->nominal_insentif)) ? "" : Model::formatHargaInd($ba_detail->nominal_insentif);
        }
        $start_date_sick = (empty($ba_detail->start_date_sick)) ? "" : Model::TanggalIndo($ba_detail->start_date_sick);
        $end_date_sick = (empty($ba_detail->end_date_sick)) ? "" : Model::TanggalIndo($ba_detail->end_date_sick);
        $phk_date = (empty($ba_detail->phk_date)) ? "" : Model::TanggalIndo($ba_detail->phk_date);
        $tanggal_td_pernyataan = (empty($ba_detail->tanggal_td_pernyataan)) ? "" : Model::TanggalIndo($ba_detail->tanggal_td_pernyataan);
        $last_payroll = (empty($ba_detail->last_payroll)) ? "" : Model::BulanIndo($ba_detail->last_payroll);
        $resign_date = (empty($ba_detail->resign_date)) ? "" : Model::TanggalIndo($ba_detail->resign_date);

        //set tanda tangan oleh
        $decree_pj = str_replace("Pj. ", "", str_replace("Pjs. ", "", $decree_title));
        $decree_title = str_replace("President Director", "Direktur Utama", $decree_pj);
        $decree_title = str_replace("Director", "Direktur", $decree_title);

        //replace first content
        $content_first_esk1 = str_replace("{manual_content_1}", $manual_content_1, $content_sk);
        $content_first_esk2 = str_replace("{manual_content_2}", $manual_content_2, $content_first_esk1);
        $content_first_esk3 = str_replace("{manual_content_3}", $manual_content_3, $content_first_esk2);
        $content_first_esk4 = str_replace("{keterangan_ba_1}", $keterangan_ba_1, $content_first_esk3);
        $content_first_esk5 = str_replace("{keterangan_ba_2}", $keterangan_ba_2, $content_first_esk4);
        $content_first_esk6 = str_replace("{keterangan_ba_3}", $keterangan_ba_3, $content_first_esk5);
        $content_first_esk7 = str_replace("{keputusan_direksi_1}", $keputusan_direksi_1, $content_first_esk6);
        $content_first_esk8 = str_replace("{keputusan_direksi_2}", $keputusan_direksi_2, $content_first_esk7);
        $content_sk = str_replace("{keputusan_direksi_3}", $keputusan_direksi_3, $content_first_esk8);
        
        $content_esk1 = str_replace("{tentang_sk}", strtoupper($master->about), $content_sk);
        $content_esk2 = str_replace("{nama}", $ba_detail->nama, $content_esk1);
        $content_esk3 = str_replace("{nik}", $ba_detail->nik, $content_esk2);
        $content_esk4 = str_replace("{posisi_lama}", $posisi_lama, $content_esk3);
        $content_esk5 = str_replace("{posisi_baru}", $posisi_baru, $content_esk4);
        $content_esk6 = str_replace("{organisasi_lama}", $ba_detail->old_organization, $content_esk5);
        $content_esk7 = str_replace("{organisasi_baru}", $ba_detail->new_organization, $content_esk6);
        $content_esk8 = str_replace("{evp_group_lama}", $ba_detail->old_egroup, $content_esk7);
        $content_esk9 = str_replace("{evp_group_baru}", $ba_detail->new_egroup, $content_esk8);
        $content_esk10 = str_replace("{directorate_lama}", $ba_detail->old_directorate, $content_esk9);
        $content_esk11 = str_replace("{directorate_baru}", $ba_detail->new_directorate, $content_esk10);
        $content_esk12 = str_replace("{bp_lama}", $ba_detail->old_bp, $content_esk11);
        $content_esk13 = str_replace("{bp_baru}", $ba_detail->new_bp, $content_esk12);
        $content_esk14 = str_replace("{bi_lama}", $ba_detail->old_bi, $content_esk13);
        $content_esk15 = str_replace("{bi_baru}", $ba_detail->new_bi, $content_esk14);
        $content_esk16 = str_replace("{kota_lama}", ucwords(strtolower($ba_detail->old_kota)), $content_esk15);
        $content_esk17 = str_replace("{kota_baru}", ucwords(strtolower($ba_detail->new_kota)), $content_esk16);
        $content_esk18 = str_replace("{lokasi_kerja_baru}", ucwords(strtolower($ba_detail->new_kota)), $content_esk17);
        $content_esk19 = str_replace("{section_lama}", $ba_detail->old_section, $content_esk18);
        $content_esk20 = str_replace("{department_lama}", $ba_detail->old_department, $content_esk19);
        $content_esk21 = str_replace("{division_lama}", $ba_detail->old_division, $content_esk20);
        $content_esk22 = str_replace("{bgroup_lama}", $ba_detail->old_bgroup, $content_esk21);
        $content_esk23 = str_replace("{area_lama}", $ba_detail->old_area, $content_esk22);
        $content_esk24 = str_replace("{section_baru}", $ba_detail->new_section, $content_esk23);
        $content_esk25 = str_replace("{unit_kerja_baru}", $ba_detail->new_organization, $content_esk24);
        $content_esk26 = str_replace("{department_baru}", $ba_detail->new_department, $content_esk25);
        $content_esk27 = str_replace("{division_baru}", $ba_detail->new_division, $content_esk26);
        $content_esk28 = str_replace("{bgroup_baru}", $ba_detail->new_bgroup, $content_esk27);
        $content_esk29 = str_replace("{area_baru}", $ba_detail->new_area, $content_esk28);
        $content_esk30 = str_replace("{sdr_sdri}", $gender, $content_esk29);
        
		if((strpos(strtolower($ba_detail->tipe), "pejabat sementara") !== false || strpos(strtolower($ba_detail->tipe), "mutasi aps") !== false || strpos(strtolower($ba_detail->tipe), "sakit berkepanjangan") !== false) && $ba_detail->level_band == 4 || strpos(strtolower($ba_detail->tipe), "mpp") !== false){
			$content_esk31 = str_replace("{tanda_tangan_oleh}", "Director Human Capital Management", $content_esk30);
		} elseif((strpos(strtolower($ba_detail->tipe), "pejabat sementara") !== false || strpos(strtolower($ba_detail->tipe), "mutasi aps") !== false || strpos(strtolower($ba_detail->tipe), "sakit berkepanjangan") !== false) && $ba_detail->level_band >= 5){
			$content_esk31 = str_replace("{tanda_tangan_oleh}", "President Director", $content_esk30);
		} else {
			$content_esk31 = str_replace("{tanda_tangan_oleh}", $decree_title, $content_esk30);
		}

        $content_esk32 = str_replace("{tanggal_sk_berlaku}", Model::TanggalIndo($tgl_berlaku), $content_esk31);
        $content_esk33 = str_replace("{periode}", $periode, $content_esk32); //masih ga tahu dapat datanya
        $content_esk34 = str_replace("{gaji_dasar}", Model::formatHargaInd($salary['gaji_dasar']), $content_esk33);
        $content_esk35 = str_replace("{tunjangan_biaya_hidup}", Model::formatHargaInd($salary['tunjangan_biaya_hidup']), $content_esk34);
        $content_esk36 = str_replace("{tunjangan_jabatan}", Model::formatHargaInd($tunjangan_jabatan), $content_esk35);
        $content_esk37 = str_replace("{tunjangan_fungsional}", Model::formatHargaInd($salary['tunjangan_fungsional']), $content_esk36);
        $content_esk38 = str_replace("{tunjangan_rekomposisi}", Model::formatHargaInd($salary['tunjangan_rekomposisi']), $content_esk37);
        $content_esk39 = str_replace("{level_tbh_lama}", $ba_detail->old_bi, $content_esk38);
        $content_esk40 = str_replace("{level_tbh_baru}", $ba_detail->new_bi, $content_esk39);
        $content_esk41 = str_replace("{level_tr_lama}", $ba_detail->old_bi, $content_esk40);
        $content_esk42 = str_replace("{nota_dinas}", $nota_dinas, $content_esk41);
        $content_esk43 = str_replace("{nomor_ba}", $nomor_ba, $content_esk42);
        $content_esk44 = str_replace("{tanggal_ba}", $tanggal_ba, $content_esk43);
        $content_esk45 = str_replace("{manual_content_1}", $manual_content_1, $content_esk44);
        $content_esk46 = str_replace("{manual_content_2}", $manual_content_2, $content_esk45);
        $content_esk47 = str_replace("{manual_content_3}", $manual_content_3, $content_esk46);
        $content_esk48 = str_replace("{keterangan_ba_1}", $keterangan_ba_1, $content_esk47);
        $content_esk49 = str_replace("{keterangan_ba_2}", $keterangan_ba_2, $content_esk48);
        $content_esk50 = str_replace("{keterangan_ba_3}", $keterangan_ba_3, $content_esk49);
        $content_esk51 = str_replace("{keputusan_direksi_1}", $keputusan_direksi_1, $content_esk50);
        $content_esk52 = str_replace("{keputusan_direksi_2}", $keputusan_direksi_2, $content_esk51);
        $content_esk53 = str_replace("{keputusan_direksi_3}", $keputusan_direksi_3, $content_esk52);
        $content_esk54 = str_replace("{nama_penyakit}", $nama_penyakit, $content_esk53);
        $content_esk55 = str_replace("{nominal_insentif}", $data_insentif, $content_esk54);
        $content_esk56 = str_replace("{level_tr_baru}", $ba_detail->new_bi, $content_esk55);
        $content_esk57 = str_replace("{label_tunjangan_jabatan}", $label_tunjangan_jabatan, $content_esk56);
		
		if((strpos(strtolower($ba_detail->tipe), "pejabat sementara") !== false || strpos(strtolower($ba_detail->tipe), "mutasi aps") !== false || strpos(strtolower($ba_detail->tipe), "sakit berkepanjangan") !== false) && $ba_detail->level_band == 4 || strpos(strtolower($ba_detail->tipe), "mpp") !== false){
			$content_esk58 = str_replace("{tanda_tangan_oleh_first_capital}", strtoupper("Director Human Capital Management"), $content_esk57);
		} elseif((strpos(strtolower($ba_detail->tipe), "pejabat sementara") !== false || strpos(strtolower($ba_detail->tipe), "mutasi aps") !== false || strpos(strtolower($ba_detail->tipe), "sakit berkepanjangan") !== false) && $ba_detail->level_band >= 5){
			$content_esk58 = str_replace("{tanda_tangan_oleh_first_capital}", strtoupper("President Director"), $content_esk57);
		} else {
			$content_esk58 = str_replace("{tanda_tangan_oleh_first_capital}", strtoupper($decree_title), $content_esk57);
		}
        
        $content_esk59 = str_replace("{cltp_reason}", $ba_detail->cltp_reason, $content_esk58);
        $content_esk60 = str_replace("{start_date_sick}", $start_date_sick, $content_esk59);
        $content_esk61 = str_replace("{end_date_sick}", $end_date_sick, $content_esk60);
        $content_esk62 = str_replace("{phk_date}", $phk_date, $content_esk61);
        $content_esk63 = str_replace("{tanggal_td_pernyataan}", $tanggal_td_pernyataan, $content_esk62);
        $content_esk64 = str_replace("{last_payroll}", $last_payroll, $content_esk63);
        $content_esk65 = str_replace("{resign_date}", $resign_date, $content_esk64);
        $content_esk66 = str_replace("{scholarship_program}", $ba_detail->scholarship_program, $content_esk65);
        $content_esk67 = str_replace("{scholarship_university}", $ba_detail->scholarship_university, $content_esk66);
        $content_esk68 = str_replace("{scholarship_level}", $ba_detail->scholarship_level, $content_esk67);
        $content_esk69 = str_replace("{alamat}", $data_employee->alamat, $content_esk68);
        $content_esk70 = str_replace("{homebase}", $homebase, $content_esk69);
        $content_esk71 = str_replace("{status_pernikahan}", $status_pernikahan, $content_esk70);
        $content_esk72 = str_replace("{jumlah_anak}", $jumlah_anak, $content_esk71);
        $content_esk73 = str_replace("{tanggal_masuk}", $tanggal_masuk, $content_esk72);
        $content_esk74 = str_replace("{daftar_keluarga}", $keluarga, $content_esk73);
        $content_esk75 = str_replace("{uang_pisah}", $uang_pisah, $content_esk74);

        //validation data 
        //if($authority == "HEAD OFFICE"){
        if($ba_detail->new_area == "HEAD OFFICE"){
            $area = "HEAD OFFICE";
        }else{
            $area = "AREA";
        }

        if(strpos($posisi_lama, "Director") !== false || strpos($posisi_baru, "Direktur") !== false){
            $band = "director";
        }else{
            $databp = explode(".",$ba_detail->new_bp);         
            $band = $databp[0];
        }

        if(
            strpos($ba_detail->new_organization, "Enterprise Resource Planning") !== false ||
            strpos($ba_detail->new_organization, "Next Generation Network") !== false ||
            strpos($ba_detail->new_organization, "Telkomsel Smart Office") !== false
        ){
            if(strpos($ba_detail->new_organization, "Enterprise Resource Planning") !== false){
                $subdir = "ERP";
            }elseif(strpos($ba_detail->new_organization, "Next Generation Network") !== false){
                $subdir = "NGN";
            }else{
                $subdir = "Telkomsel Smart Office Champion";
            }
        }else{
            /*if($area == "AREA"){
                if(strpos($ba_detail->new_directorate, "Sales Directorate") !== false){
                    $subdir = $ba_detail->new_directorate." Area";
                }else{
                    $subdir = $ba_detail->new_directorate;
                }
            }else{
                $subdir = $ba_detail->new_directorate;
            }*/
            $subdir = $ba_detail->new_directorate;
        }

        //get data eSK Code Params
        $esk_code = EskCodeParam::find()
        ->where('band = "'.trim(preg_replace('/\s\s+/', ' ', $band)).'" AND directorate = "'.trim(preg_replace('/\s\s+/', ' ', $subdir)).'"')
        ->all();

        $content_esk76 = $content_esk75;
        foreach($esk_code as $code){
            $hasil_content_esk = str_replace($code->code, $code->value, $content_esk76);
            $content_esk76 = $hasil_content_esk;
        }

        //get data eSK Code Params if band/directorate blank dan dari data dari employee table 
        $esk_code_emp = EskCodeParam::find()->where('band = "" AND directorate = "" AND is_from_employee = 1')->all();
        $content_esk77 = $content_esk76;
        foreach($esk_code_emp as $code){
            //untuk homebase/kode_kota/
            $code_data = $code->value;
            if($code->value == "homebase" || $code->value == "kode_kota"){
                //get data kota 
                $city = City::find()->where(['code' => $data_emp->$code_data])->one();
                $hasil_content_esk_emp = str_replace($code->code, ucwords(strtolower($city->name)), $content_esk77);
            }elseif(strpos($code->code, "tanggal") !== false || strpos($code->code, "date") !== false){
                $hasil_content_esk_emp = str_replace($code->code, Model::TanggalIndo($data_emp->$code_data), $content_esk77);
            }else{
                $hasil_content_esk_emp = str_replace($code->code, $data_emp->$code_data, $content_esk77);
            }
            $content_esk77 = $hasil_content_esk_emp;
        }

        //get data eSK Code Params if band/directorate blank dan ada valuenya 
        $esk_code_other = EskCodeParam::find()->where('band = "" AND directorate = ""')->all();
        $content_esk78 = $content_esk77;
        foreach($esk_code_other as $code){
            $hasil_content_esk_other = str_replace($code->code, $code->value, $content_esk78);
            $content_esk78 = $hasil_content_esk_other;
        }
		$content_esk79 = str_replace("{gaji_dasar_bss}", Model::formatHargaInd($ba_detail->gaji_dasar_nss), $content_esk78);
		$content_esk80 = str_replace("{tbh_bss}", Model::formatHargaInd($ba_detail->tbh_nss), $content_esk79);
		$content_esk81 = str_replace("{tunjangan_rekomposisi_bss}", Model::formatHargaInd($ba_detail->tunjangan_rekomposisi_nss), $content_esk80);
		$content_esk82 = str_replace("{tunjangan_jabatan_bss}", Model::formatHargaInd($ba_detail->tunjab_nss), $content_esk81);
		$content_esk83 = str_replace("{level_posisi}", $ba_detail->level_band, $content_esk82);
		$content_esk84 = str_replace("{kr_organisasi_bss}", $ba_detail->kr_organisasi_bss, $content_esk83);
		$content_esk85 = str_replace("{persen_biaya_hidup_bss}", number_format($ba_detail->persen_biaya_hidup_bss,2), $content_esk84);
		$content_esk86 = str_replace("{persen_rekom_bss}", number_format($ba_detail->persen_rekom_bss,2), $content_esk85);
		$content_esk87 = str_replace("{level_gaji}", $ba_detail->level_gaji, $content_esk86);
		$content_esk88 = str_replace("{band}", $ba_detail->band, $content_esk87);
		$content_esk89 = str_replace("{label_tunjangan_jabatan_new}", $label_tunjangan_jabatan_new, $content_esk88);
			
        $content_esk_all = $content_esk89;

        return $content_esk_all;
    }
}
