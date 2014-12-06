<?php

class Laporan_mingguanController extends Controller
{
	public $layout='main';

	public function init()
	{
		if (Yii::app()->user->isGuest) 
		{
			$this->redirect(array("site/login"));
		}
		if (Yii::app()->user->status !== 'owner') 
		{
			$this->redirect(array("dashboard/index"));
		}
		$this->widget('SetConfig');
	}

	public function actionIndex()
	{
		$laporan_mingguan_start = (array_key_exists('laporan_mingguan_start',$_SESSION) ? $_SESSION['laporan_mingguan_start'].' 00:00:00' : date('d/m/Y H:i:s'));
		$laporan_mingguan_end = (array_key_exists('laporan_mingguan_end',$_SESSION) ? $_SESSION['laporan_mingguan_end'].' 23:59:59' : date('d/m/Y H:i:s'));

		$laporan_mingguan_start_to_str = strtotime(str_replace("/", "-", $laporan_mingguan_start));
		$laporan_mingguan_end_to_str = strtotime(str_replace("/", "-", $laporan_mingguan_end));

		$graph = $list= Yii::app()->db->createCommand('SELECT count(id_master_transaksi) as jumlah_pasien, SUBSTRING(created_at,9,2) as tanggal FROM `tbl_master_transaksi` WHERE UNIX_TIMESTAMP(created_at)>='.$laporan_mingguan_start_to_str.' and UNIX_TIMESTAMP(created_at)<='.$laporan_mingguan_end_to_str.' group by SUBSTRING(created_at,6,5) order by tanggal ASC')->queryAll();

		$graph = Yii::app()->db->createCommand('SELECT SUBSTRING(a.created_at,9,2) as created, a.id_master_transaksi, a.total, b.komisi_dokter, b.komisi_perawat FROM `tbl_master_transaksi` a left join tbl_perawatan b on a.id_perawatan=b.id_perawatan WHERE UNIX_TIMESTAMP(a.created_at)>='.$laporan_mingguan_start_to_str.' and UNIX_TIMESTAMP(a.created_at)<='.$laporan_mingguan_end_to_str.' group by SUBSTRING(a.created_at,6,5) order by created ASC')->queryAll();

		$keuntungan = 0;
		$arr = array();
		foreach($graph as $g){
			$dokter = Yii::app()->db->createCommand('SELECT count(a.id_master_transaksi) as jum_dokter FROM tbl_transaksi_dokter a where id_master_transaksi="'.$g['id_master_transaksi'].'"')->queryAll();
			
			$perawat = Yii::app()->db->createCommand('SELECT count(a.id_master_transaksi) as jum_perawat FROM tbl_transaksi_perawat a where id_master_transaksi="'.$g['id_master_transaksi'].'"')->queryAll();
			
			$obat = Yii::app()->db->createCommand('select y.keuntungan*x.jumlah as total_keuntungan, y.harga_jual*x.jumlah as total_jual_obat, y.harga_pokok*x.jumlah as total_pokok_obat  from tbl_transaksi_obat x left join tbl_barang y on x.id_obat=y.id_barang where x.id_master_transaksi="'.$g['id_master_transaksi'].'"')->queryAll();

			$komisi_dokter = $dokter[0]['jum_dokter']*$g['komisi_dokter'];
			$komisi_perawat = $perawat[0]['jum_perawat']*$g['komisi_perawat'];
			$total_keuntungan = 0;
			$total_pokok_obat = 0;
			$total_jual_obat = 0;
			foreach($obat as $o){
				$total_pokok_obat = $total_pokok_obat+$o['total_pokok_obat'];
				$total_jual_obat = $total_jual_obat+$o['total_jual_obat'];
				$total_keuntungan = $total_keuntungan+$o['total_keuntungan'];
			}
			$d['created_at'] = $g['created'];
			$d['keuntungan'] = $g['total']-($total_jual_obat-($total_jual_obat-$total_pokok_obat))-$komisi_dokter-$komisi_perawat;
			$d['penjualan'] = $g['total'];
			array_push($arr, $d);
		}
		$hasil = array();
		$temp = '';

		$data_summ = array();
		foreach ( $arr as $value ) {
		    $data_summ[ 'keuntungan_'.$value['created_at'] ] = 0;
		    $data_summ[ 'penjualan_'.$value['created_at'] ] = 0;
		}

		foreach ( $arr as $list ) {
		    $keuntungan = str_replace( ",", ".", $list['keuntungan'] ) * 1;
		    $penjualan = str_replace( ",", ".", $list['penjualan'] ) * 1;
		    $data_summ[ 'keuntungan_'.$list['created_at'] ] += $keuntungan;
		    $data_summ[ 'penjualan_'.$list['created_at'] ] += $penjualan;
		}

		//var_dump($data_summ);die;

		$arr_date = array();
		$arr_keuntungan = array();
		$arr_penjualan = array();
		$keys = array_keys($data_summ);
		foreach($keys as $key=>$date){
			$explode_month = explode("_", $date);
			
			if($key%2===0){
				array_push($arr_date, $explode_month[1]);
			}
		}

		$n = 0;
		foreach($data_summ as $key=>$val){
			if($n%2===0){
				array_push($arr_penjualan, $val);
			}
			else{
				array_push($arr_keuntungan, $val);
			}
			$n++;
		}

		$this->render('index', array(
				'arr_date' => $arr_date,
				'arr_keuntungan' => $arr_keuntungan,
				'arr_penjualan' => $arr_penjualan,
			));
	}

	public function actionSet()
	{
		$_SESSION['laporan_mingguan_start'] = $_POST['laporan_mingguan_start'];
		$_SESSION['laporan_mingguan_end'] = $_POST['laporan_mingguan_end'];

		$this->redirect(array("laporan_mingguan/index"));
	}

    protected function Bulan($bulan)
    {
        $list_bulan = array("Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
        return($list_bulan[$bulan]);
    }
}