<?php
require_once "../db/class_db.php";
require_once "../timer/class_timer.php";

// 10 seconds is too long TBH .. the hold up is probably in the DB code .. just my guess
$A = new Timer();

//takes 2k of random data, converts it to b64 (iterations times)  and then cuts out 16 bytes
// minimum of 16 passes
function genKey (int $iterations, int $sample_size) : string
{
	$watchdog = new Timer();
	$retry = 0;
	RETRY:
	$retry++;
	if ($watchdog->read() >= 180) {
		throw new Exception("The local watchdog detected that genKey has reached 3 minutes",0x88);
	}
	if ($retry > 2) {
		if ($retry > 50) {
			echo "retry $retry: committing suicide to escape the loop" . PHP_EOL;
			exit(127);
		}
		echo "retry $retry:potential loop detected, press ctrl-c or kill process '" . getmypid() . "' to escape" . PHP_EOL;
	}
	$hash = "";
	if ($iterations < 16) $iterations = 16;
	// 4M
	if ($sample_size < 262144) $sample_size=262144;
	$timing = 0.0;
	$k = new Timer();
	for ($i=0; $i != $iterations;  $i++)
	{
		$t = new Timer();
		// bytes|b64|bz2 then concat
		// undo: split into blocks of $sample_size | unbz2 | unb64 -- do we need to? no
		$hash .= bzcompress(base64_encode(random_bytes($sample_size)));
		$timing += $t->stop();
	}
	echo "after ". ($timing) ." seconds, hash is currently " . strlen($hash) . " long" . PHP_EOL;
	// now we have iterations*16 bytes of data... let's take a 16-byte chomp of that.
	$new_hash="";
	for ($i=0; $i < 16; $i++)
	{
		// let's grab randomly chosen bingo balls from the iterations*16 we created for our final product.
		// min 262144*16=4194304 (not many) but better. let's reverse the input1 and input2 user does iterations of 256k min of 16 still
		$new_hash .= $hash[random_int(1,strlen($hash))];// <-- this is important because it doesn't give a shit about all that up there
																										// <-- just cares how long the string is and for 16its, it pulls out between 1 and maxlen
		
	}
	echo "key generation took " . $k->stop() . " seconds to generate " . ($iterations*$sample_size) . " bytes and to choose only 16 of those.";
	// now we will base64 it once again and we should be good.
	echo "strlen " . strlen($new_hash) . " = $new_hash" . PHP_EOL;
	if (strlen($new_hash) === 16)
	{
		return (substr(base64_encode($new_hash),0,16));
	}
	else
	{
		echo "strlen of new_hash is only " . strlen($new_hash) . " and it should be 16. Retrying the procedure" . PHP_EOL;
		goto RETRY;
	}
}

function getCurrentHashValue (string $filename,string $key="") : string
{
  if (empty($filename)) {
    return ("");
  }
	if (empty($key)) {
		$key = genKey(150,2048);
	}
	if (empty($key)) {
		echo "The bitter winds of LMK<INENsak have stolen my last breath" . PHP_EOL;
		exit (254);
	}
  try {
    $data = file_get_contents($filename);
    if ($data === false) throw new Exception ("Failed to read the specified file",251);
    if (empty($data)) throw new Exception ("The specified file contained no usable data",252);
    if (!is_string($data)) throw new Exception ("The specified file returned no usable data",253);
		if (($hashValue = hash_hmac("sha256",$data,$key)) === false) 
			throw new Exception ("Invalid algorithm supplied to hash_hmac()",250);
  } catch (Exception $dataFail) {
    echo $dataFail->getMessage() . PHP_EOL;
    exit($dataFail->getCode());
  }
  return ($data);
}

/////

if (!isset($envision) || (!is_a($envision,"EnVision"))) {
	if ((file_exists(getenv("ENV_VISIONDIR")."/objects/cereal"))!==false) {
		$envision = new EnVision(getenv("ENV_VISIONDIR")."/objects/cereal",true);
	}
	if ((!isset($envision))|| (!is_object($envision)) || (!is_a($envision,"EnVision"))) $envision = new EnVision();
}

$db = new db();
$cFile = "/env/vision/172.20.21.1/objects/cereal2";
try {
	if (empty($hashKey = genKey(150,2048))) throw new Exception ("Failed to retrieve a hash key from genKey()",251);
	$hash_v = getCurrentHashValue($cFile,$hashKey);
	if (($hashValue = hash_hmac("sha256",$hash_v,$hashKey)) === false) throw new Exception ("Invalid algorithm supplied to hash_hmac()",250);
} catch (Exception $hashFail) {
	echo $hashFail->getMessage() . PHP_EOL;
	exit($hashFail->getCode());
}

$db->sqlstr = "INSERT INTO cereal_hash VALUES ("
. "nextval('public.cereal_hash_hash_id_seq'::text),"
. "now(),"
. "'$hashValue',"
. "'$hashKey',"
. "'f'::boolean) RETURNING *";
if (!$db->query()) {
	echo "FATAL: Failed to insert the hash key into the database" . PHP_EOL;
	exit(249);
} else {
	$hres = $db->row();
}
echo "$hashKey" . PHP_EOL;
echo "" . $hres['hash_key'] . "" . PHP_EOL;
$hashValue = "";
$hashKey = "";

$cFile = "/env/vision/172.20.21.1/nse/objects/cereal2";
$hash_v = getCurrentHashValue($cFile,$hres['hash_key']);
$hashValue = hash_hmac("sha256",$hash_v,$hres['hash_key']);
if (hash_equals($hres['hash_value'],$hashValue)) {
	echo "they match ... woo..." . PHP_EOL;
	echo "Updating the database. .. " . PHP_EOL;
	$db->sqlstr = "UPDATE cereal_hash SET hash_is_valid='t' WHERE hash_id=". $hres['hash_id'];
	$db->query();
} else {
	echo $hres['hash_value'] . " and " . $hash_v ." did not match" . PHP_EOL;
	echo "the hash is invalid, something is broken" . PHP_EOL;
}

//$hashValue = hash_hmac("sha256",$cereal,$key,true);


//if (hash_equals($hash_expected,$hash_given)) {
	
echo "Total application time took " . $A->stop() . " seconds. Cya!" . PHP_EOL;
?>
