F$<?php
require_once(dirname(__FILE__) . "/../bin/config.php");
require_once(WWW_DIR . "/lib/rarinfo/par2info.php");
require_once(WWW_DIR . "/lib/rarinfo/archiveinfo.php");
require_once(WWW_DIR . "/lib/rarinfo/zipinfo.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/releases.php");
require_once(WWW_DIR . "/lib/releaseimage.php");
require_once(WWW_DIR . "/lib/releaseextra.php");
require_once(WWW_DIR . "/lib/groups.php");
require_once(WWW_DIR . '/lib/nntp.php');
require_once(WWW_DIR . "/lib/nzb.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/Tmux.php");
require_once(WWW_DIR . "/lib/amazon.php");
require_once(WWW_DIR . "/lib/genres.php");
require_once(WWW_DIR . "/lib/tvrage.php");
require_once(WWW_DIR . "/lib/anidb.php");
require_once("consoletools.php");
require_once("ColorCLI.php");
require_once("nzbcontents.php");
require_once("namefixer.php");
require_once("Info.php");
require_once("prehash.php");
require_once("Sharing.php");
require_once("TraktTv.php");
require_once("Film.php");

/**
 * Class PProcess
 */
class PProcess
{

	/**
	 * @TODO: Remove ffmpeg_image_time from DB..
	 */

	const BAD_FILE = 10; // Possibly broken RAR/ZIP.
	/**
	 * @var ColorCLI
	 */
	private $c;

	/**
	 * @var DB
	 */
	private $db;
	/**
	 * @var Groups
	 */
	private $groups;

	/**
	 * @var Nfo
	 */
	private $Nfo;

	/**
	 * @var ReleaseFiles
	 */
	private $releaseFiles;

	/**
	 * Object containing site settings.
	 *
	 * @var bool|stdClass
	 */
	private $site;

	/**
	 * How many additional to process per run.
	 *
	 * @var int
	 */
	private $addqty;

	/**
	 * Have we initiated the objects used for processAdditional?
	 *
	 * @var bool
	 */
	private $additionalInitiated;

	/**
	 * Add par2 info to rar list?
	 *
	 * @var bool
	 */
	private $addpar2;

	/**
	 * Should we echo to CLI?
	 *
	 * @var bool
	 */
	private $echooutput;

	/**
	 * Max file size to post process.
	 *
	 * @var int
	 */
	private $maxsize;

	/**
	 * Instance of NameFixer.
	 *
	 * @var NameFixer
	 */
	protected $nameFixer;

	/**
	 * Constructor.
	 *
	 * @param bool $echoOutput Echo to CLI or not?
	 */
	public function __construct($echoOutput = false)
	{
		//\\ Various.
		$this->echooutput = $echoOutput;
		$this->additionalInitiated = false;
		//\\

		//\\ Class instances.
		$this->c = new ColorCLI();
		$this->db = new DB();
		$this->groups = new Groups();
		$this->nameFixer = new Namefixer($this->echooutput);
		$this->Nfo = new Info($this->echooutput);
		$this->releaseFiles = new ReleaseFiles();
		$s = new sites();
		$t = new Tmux();
		//\\

		//\\ Site object.
		$this->site = $s->get();
		$this->tmux = $t->get();

		//\\

		//\\ Site settings.
		$this->addqty = (!empty($this->tmux->maxaddprocessed)) ? (int)$this->tmux->maxaddprocessed : 25;
		$this->addpar2 = ($this->tmux->addpar2 === '0') ? false : true;
		$this->maxsize = (!empty($this->tmux->maxsizetopostprocess)) ? (int)$this->tmux->maxsizetopostprocess : 100;
		//\\
	}

	/**
	 * Go through every type of post proc.
	 *
	 * @param $nntp
	 *
	 * @return void
	 */
	public function processAll($nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(pprocess->processAll).\n"));
		}

		$this->processPrehash($nntp);
		$this->processAdditional($nntp);
		$this->processNfos('', $nntp);
		$this->processSharing($nntp);
		$this->processMovies();
		$this->processMusic();
		$this->processGames();
		$this->processAnime();
		$this->processTv();
		$this->processBooks();
	}

	/**
	 * Lookup anidb if enabled - always run before tvrage.
	 *
	 * @return void
	 */
	public function processAnime()
	{
		if ($this->site->lookupanidb === '1') {
			$anidb = new AniDB($this->echooutput);
			$anidb->animetitlesUpdate();
			$anidb->processAnimeReleases();
		}
	}

	/**
	 * Process books using amazon.com.
	 *
	 * @return void
	 */
	public function processBooks()
	{
		if ($this->site->lookupbooks !== '0') {
			$books = new Book($this->echooutput);
			$books->processBookReleases();
		}
	}

	/**
	 * Lookup games if enabled.
	 *
	 * @return void
	 */
	public function processGames()
	{
		if ($this->site->lookupgames !== '0') {
			$console = new Console($this->echooutput);
			$console->processConsoleReleases();
		}
	}

	/**
	 * Lookup imdb if enabled.
	 *
	 * @param string $releaseToWork
	 *
	 * @return void
	 */
	public function processMovies($releaseToWork = '')
	{
		if ($this->site->lookupimdb === '1') {
			$movie = new Film($this->echooutput);
			$movie->processMovieReleases($releaseToWork);
		}
	}

	/**
	 * Lookup music if enabled.
	 *
	 * @return void
	 */
	public function processMusic()
	{
		if ($this->site->lookupmusic !== '0') {
			$music = new Music($this->echooutput);
			$music->processMusicReleases();
		}
	}

	/**
	 * Process nfo files.
	 *
	 * @param string $releaseToWork
	 * @param        $nntp
	 *
	 * @return void
	 */
	public function processNfos($releaseToWork = '', $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(pprocess->processNfos).\n"));
		}

		if ($this->site->lookupnfo === '1') {
			$this->Nfo->processNfoFiles($releaseToWork, $this->site->lookupimdb, $this->site->lookuptvrage, $groupID = '', $nntp);
		}
	}

	/**
	 * Fetch titles from predb sites.
	 *
	 * @param $nntp
	 *
	 * @return void
	 */
	public function processPrehash($nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(PostProcess->processPreDB).\n"));
		}

		$preDB = new PreHash($this->echooutput);
		$titles = $preDB->updatePre();
		$preDB->checkPre($nntp);
		if ($titles > 0) {
			$this->doEcho($this->c->header('Fetched ' . number_format($titles) . ' new title(s) from PreDB sources.'));
		}
	}

	/**
	 * Process comments.
	 *
	 * @param NNTP $nntp
	 */
	public function processSharing(&$nntp)
	{
		$sharing = new Sharing($this->db, $nntp);
		$sharing->start();
	}

	/**
	 * Process all TV related releases which will assign their series/episode/rage data.
	 *
	 * @param string $releaseToWork
	 *
	 * @return void
	 */
	public function processTv($releaseToWork = '')
	{
		if ($this->site->lookuptvrage === '1') {
			$tvRage = new TvRage($this->echooutput);
			$tvRage->processTvReleases($releaseToWork, $this->site->lookuptvrage === '1');
		}
	}

	/**
	 * Attempt to get a better name from a par2 file and categorize the release.
	 *
	 * @note Called from NZBContents.php
	 *
	 * @param string $messageID MessageID from NZB file.
	 * @param int    $relID     ID of the release.
	 * @param int    $groupID   Group ID of the release.
	 * @param NNTP   $nntp      Class NNTP
	 * @param int    $show      Only show result or apply iy.
	 *
	 * @return bool
	 */
	public function parsePAR2($messageID, $relID, $groupID, $nntp, $show)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(PProcess->parsePAR2).\n"));
		}

		if ($messageID === '') {
			return false;
		}

		$query = $this->db->queryOneRow(
			'SELECT ID, groupID, categoryID, searchname, ' .
			(DB_TYPE === 'mysql' ? 'UNIX_TIMESTAMP(postdate)' : 'extract(epoch FROM postdate)') .
			' as postdate, ID as releaseID  FROM releases WHERE isrenamed = 0 AND ID = ' .
			$relID
		);

		if ($query['categoryID'] != Category::CAT_MISC_OTHER) {
			return false;
		}

		// Get the PAR2 file.
		$par2 = $nntp->getMessages($this->functions->getByNameByID($groupID), $messageID);
		if ($nntp->isError($par2)) {
			return false;
		}

		// Put the PAR2 into Par2Info, check if there's an error.
		$par2info = new Par2Info();
		$par2info->setData($par2);
		if ($par2info->error) {
			return false;
		}

		// Get the file list from Par2Info.
		$files = $par2info->getFileList();
		if ($files !== false && count($files) > 0) {

			$relFiles = 0;
			$foundName = false;

			// Loop through the files.
			foreach ($files as $file) {

				// If we found a name and have more than 10 files in the DB break out.
				if ($foundName === true && $relFiles > 10) {
					break;
				}

				if (!array_key_exists('name', $file)) {
					continue;
				}

				// Add to release files.
				if ($this->addpar2 && $relFiles < 11 &&
					$this->db->queryOneRow(
						sprintf('SELECT ID FROM releasefiles WHERE releaseID = %d AND name = %s',
							$relID, $this->db->escapeString($file['name'])
						)
					) === false
				) {

					// Try to add the files to the DB.
					if ($this->releaseFiles->add($relID, $file['name'], $file['size'], $query['postdate'], 0)) {
						$relFiles++;
					}
				}

				// Try to get a new name.
				if ($foundName === false) {
					$query['textstring'] = $file['name'];
					if ($this->nameFixer->checkName($query, 1, 'PAR2, ', 1, $show) === true) {
						$foundName = true;
					}
				}
			}

			// If we found some files.
			if ($relFiles > 0) {
				$this->c->debug('parsePAR2', 'Added ' . $relFiles . ' releasefiles from PAR2 for ' . $query['searchname']);

				// Update the file count with the new file count + old file count.
				$this->db->exec(sprintf('UPDATE releases SET rarinnerfilecount = rarinnerfilecount + %d WHERE ID = %d', $relFiles, $relID));
			}
			if ($foundName === true) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Echo messages if echo is on.
	 *
	 * @param $str
	 */
	protected function doEcho($str)
	{
		$this->c->doEcho($this->c->header($str));
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////// Start of ProcessAdditional methods ////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * How long should the video sample be?
	 *
	 * @var int
	 */
	private $ffMPEGDuration;

	/**
	 * How many parts to download before giving up.
	 *
	 * @var int
	 */
	private $partsQTY;

	/**
	 * How many attempts to check for a password.
	 *
	 * @var int
	 */
	private $passChkAttempts;

	/**
	 * How many articles to download when getting a JPG.
	 *
	 * @var int
	 */
	private $segmentsToDownload;

	/**
	 * Path to store audio samples.
	 *
	 * @var string
	 */
	private $audSavePath;

	/**
	 * Path to temp folder.
	 *
	 * @var string
	 */
	private $mainTmpPath;

	/**
	 * Path to store files temporarily.
	 *
	 * @var string
	 */
	private $tmpPath;

	/**
	 * @var ReleaseImage
	 */
	private $releaseImage;

	/**
	 * @var int
	 */
	private $filesAdded;

	/**
	 * @var bool
	 */
	private $noNFO;

	/**
	 * @var bool
	 */
	private $password;

	/**
	 * Regex of common audio file extensions.
	 *
	 * @var string
	 */
	private $audioFileRegex;

	/**
	 * Regex of common book extensions.
	 *
	 * @var string
	 */
	private $ignoreBookRegex;

	/**
	 * Regex of common usenet binary extensions,
	 *
	 * @var string
	 */
	private $supportFiles;

	/**
	 * Regex of common video file extensions.
	 *
	 * @var string
	 */
	private $videoFileRegex;

	/**
	 * @var bool
	 */
	private $blnTookSample;

	/**
	 * @var bool
	 */
	private $blnTookAudioinfo;

	/**
	 * @var bool
	 */
	protected $blnTookAudioSample;

	/**
	 * @var bool
	 */
	private $blnTookMediainfo;

	/**
	 * @var bool
	 */
	private $blnTookJPG;

	/**
	 * @var bool
	 */
	private $blnTookVideo;

	/**
	 * @var int
	 */
	private $sum;

	/**
	 * @var int
	 */
	private $size;

	/**
	 * @var int
	 */
	private $segsize;

	/**
	 * @var int
	 */
	private $adj;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var bool
	 */
	private $ignoreNumbered;

	/**
	 * @var bool
	 */
	protected $processSample;

	/**
	 * @var bool
	 */
	protected $processVideo;

	/**
	 * @var bool
	 */
	protected $processMediaInfo;

	/**
	 * @var bool
	 */
	protected $processAudioInfo;

	/**
	 * @var bool
	 */
	protected $processAudioSample;

	/**
	 * @var bool
	 */
	protected $processJPGSample;

	/**
	 * @var bool
	 */
	protected $processPasswords;

	/**
	 * @var ReleaseExtra
	 * @access protected
	 */
	protected $releaseExtra;

	/**
	 * @var bool
	 * @access protected
	 */
	protected $newfiles;

	/**

	/**
	 * Initiate objects used in processAdditional.
	 *
	 * @return void
	 */
	protected function initAdditional()
	{
		// Check if the objects are already initiated.
		if ($this->additionalInitiated) {
			return;
		}

		//\\ Class instances.
		$this->releaseExtra = new ReleaseExtra();
		$this->releaseImage = new ReleaseImage();
		//\\

		//\\ Site settings.
		$this->ffMPEGDuration = (!empty($this->tmux->ffmpeg_duration)) ? (int)$this->tmux->ffmpeg_duration : 5;
		$this->partsQTY = (!empty($this->tmux->maxpartsprocessed)) ? (int)$this->tmux->maxpartsprocessed : 3;
		$this->passChkAttempts = (!empty($this->tmux->passchkattempts)) ? (int)$this->tmux->passchkattempts : 1;
		$this->segmentsToDownload = (!empty($this->tmux->segmentstodownload)) ? (int)$this->tmux->segmentstodownload : 2;
		$this->processSample = empty($this->site->ffmpegpath) ? false : true;
		$this->processVideo = ($this->tmux->processvideos === '0') ? false : true;
		$this->processMediaInfo = $this->processAudioInfo = empty($this->site->mediainfopath) ? false : true;
		$this->processAudioSample = ($this->site->processaudiosample == '0') ? false : true;
		$this->processJPGSample = ($this->tmux->processjpg === '0') ? false : true;
		$this->processPasswords = ((($this->site->checkpasswordedrar === '0') ? false : true) && (empty($this->site->unrarpath) ? false : true));
		//\\

		//\\ Paths.
		$this->audSavePath = WWW_DIR . 'covers/audio/';
		$this->jpgSavePath = WWW_DIR . 'covers/sample/';
		$this->imgSavePath = WWW_DIR . 'covers/console/';
		$this->vidSavePath = WWW_DIR . 'covers/video/';
		$this->mainTmpPath = $this->site->tmpunrarpath;
		$this->tmpPath = $this->mainTmpPath;
		//\\

		//\\ Various.
		$this->filesAdded = $this->sum = $this->size = $this->segsize = $this->adj = 0;
		$this->noNFO = $this->password = $this->ignoreNumbered = $this->blnTookVideo = false;
		$this->blnTookSample = $this->blnTookAudioinfo = $this->blnTookJPG = $this->blnTookMediainfo = false;
		$this->name = '';
		//\\

		//\\ Regex.
		$this->audioFileRegex = '\.(AAC|AIFF|APE|AC3|ASF|DTS|FLAC|MKA|MKS|MP2|MP3|RA|OGG|OGM|W64|WAV|WMA)';
		$this->ignoreBookRegex = '/\b(epub|lit|mobi|pdf|sipdf|html)\b.*\.rar(?!.{20,})/i';
		$this->supportFiles = '/\.(vol\d{1,3}\+\d{1,3}|par2|srs|sfv|nzb';
		$this->videoFileRegex = '\.(AVI|F4V|IFO|M1V|M2V|M4V|MKV|MOV|MP4|MPEG|MPG|MPGV|MPV|OGV|QT|RM|RMVB|TS|VOB|WMV)';

		// Note that we initiated the objects.
		$this->additionalInitiated = true;
	}

	/**
	 * Run processAdditional threaded.
	 *
	 * @param string $releaseToWork
	 * @param        $nntp
	 *
	 * @return void
	 */
	public function processAdditionalThreaded($releaseToWork = '', $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(PProcess->processAdditionalThreaded).\n"));
		}

		$this->processAdditional($nntp, $releaseToWork);
	}

	/**
	 * Check for passworded releases, RAR contents and Sample/Media info.
	 *
	 * @note Called externally by tmux/bin/update_per_group and update/postprocess.php
	 *
	 * @param NNTP   $nntp          Class NNTP
	 * @param string $releaseToWork String containing SQL results. Optional.
	 * @param string $groupID       Group ID. Optional
	 *
	 * @return void
	 */
	public function processAdditional($nntp, $releaseToWork = '', $groupID = '')
	{
		$groupID = ($groupID === '' ? '' : 'AND groupID = ' . $groupID);

		// Get out all releases which have not been checked more than max attempts for password.
		$totResults = 0;
		$result = [];
		if ($releaseToWork === '') {

			$i = -6;
			$limit = $this->addqty;
			// Get releases starting from -6 password status until we reach our max limit set in site or we reach -1 password status.
			while (($totResults <= $limit) && ($i <= -1)) {

				$qResult = $this->db->query(
					sprintf('
						SELECT r.ID, r.guid, r.name, c.disablepreview, r.size, r.groupID,
							r.nfostatus, r.completion, r.categoryID, r.searchname
						FROM releases r
						LEFT JOIN category c ON c.ID = r.categoryID
						WHERE r.size < %d
						%s
						AND r.passwordstatus = %d
						AND (r.haspreview = -1 AND c.disablepreview = 0)
						ORDER BY postdate
						DESC LIMIT %d',
						$this->maxsize * 1073741824, $groupID, $i, $limit
					)
				);

				// Get the count of rows we got from the query.
				$currentCount = count($qResult);

				if ($currentCount > 0) {

					// Merge the results.
					$result += $qResult;

					// Decrement so we don't get more than the max user specified value.
					$limit -= $currentCount;

					// Update the total results.
					$totResults += $currentCount;

					// Echo how many we got for this query.
					$this->doEcho('Passwordstatus = ' . $i . ': Available to process = ' . $currentCount);
				}
				$i++;
			}
		} else {

			$pieces = explode('           =+=            ', $releaseToWork);
			$result = array(
				array(
					'ID'             => $pieces[0],
					'guid'           => $pieces[1],
					'name'           => $pieces[2],
					'disablepreview' => $pieces[3],
					'size'           => $pieces[4],
					'groupID'        => $pieces[5],
					'nfostatus'      => $pieces[6],
					'categoryID'     => $pieces[7],
					'searchname'     => $pieces[8]
				)
			);
			$totResults = 1;
		}

		$resCount = $startCount = $totResults;
		if ($resCount > 0) {
			// Start up the required objects.
			$this->initAdditional();

			if ($this->echooutput && $resCount > 1) {
				$this->doEcho('Additional post-processing, started at: ' . date('D M d, Y G:i a'));
				$this->doEcho('Downloaded: (xB) = yEnc article, f= failed ;Processing: z = zip file, r = rar file');
				$this->doEcho('Added: s = sample image, j = jpeg image, A = audio sample, a = audio mediainfo, v = video sample');
				$this->doEcho('Added: m = video mediainfo, n = nfo, ^ = file details from inside the rar/zip');
			}

			$nzb = new NZB($this->echooutput);

			// Loop through the releases.
			foreach ($result as $rel) {
				if ($this->echooutput) {
					echo $this->c->primaryOver("[" .
						($releaseToWork === ''
							? $startCount--
							: $rel['ID']
						) . '][' . $this->readableBytesString($rel['size'])
						. ']'
					);
				}

				$this->c->debug('processAdditional', 'Processing ' . $rel['searchname']);

				// Per release defaults.
				$this->tmpPath = $this->mainTmpPath . $rel['guid'];
				if (!is_dir($this->tmpPath)) {
					$old = umask(0777);
					@mkdir($this->tmpPath, 0777, true);
					@chmod($this->tmpPath, 0777);
					@umask($old);

					if (!is_dir($this->tmpPath)) {

						$error = "Unable to create directory: {$this->tmpPath}";
						$this->c->debug('processAdditional', $error);
						if ($this->echooutput) {
							echo $this->c->error($error);
						}

						// Decrement password status.
						$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
						continue;
					}
				}

				$nzbPath = $nzb->getNZBPath($rel['guid']);
				if (!is_file($nzbPath)) {
					// The nzb was not located. decrement the password status.
					$this->c->debug('processAdditional', 'NZB not found for releaseGUID: ' . $rel['guid']);
					$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
					continue;
				}

				// Turn on output buffering.
				ob_start();

				// Decompress the NZB.
				@readgzfile($nzbPath);

				// Read the nzb into memory.
				$nzbFile = ob_get_contents();

				// Clean (erase) the output buffer and turn off output buffering.
				ob_end_clean();

				// Get a list of files in the nzb.
				$nzbFiles = $nzb->nzbFileList($nzbFile);
				if (count($nzbFiles) === 0) {
					// There does not appear to be any files in the nzb, decrement password status.
					$this->c->debug('processAdditional', 'NZB file is empty: ' . $rel['guid']);
					$this->db->exec('UPDATE releases SET passwordstatus = passwordstatus - 1 WHERE ID = ' . $rel['ID']);
					continue;
				}

				// Sort the files.
				usort($nzbFiles, 'PProcess::sortRAR');

				// Only process for samples, previews and images if not disabled.
				$this->blnTookSample = ($this->processSample ? false : true);
				$this->blnTookSample = (($rel['disablepreview'] === '1') ? true : false);
				$this->blnTookVideo = ($this->processVideo ? false : true);
				$this->blnTookMediainfo = ($this->processMediaInfo ? false : true);
				$this->blnTookAudioinfo = ($this->processAudioInfo ? false : true);
				$this->blnTookAudioSample = ($this->processAudioSample ? false : true);
				$this->blnTookJPG = ($this->processJPGSample ? false : true);

				// Reset and set certain variables.
				$passStatus = array(Releases::PASSWD_NONE);
				$sampleMsgID = $jpgMsgID = $audioType = $mID = array();
				$mediaMsgID = $audioMsgID = '';
				$hasRar = $ignoredBooks = $failed = $this->filesAdded = $notInfinite = 0;
				$this->password = $this->noNFO = $bookFlood = false;
				$groupName = $this->functions->getByNameByID($rel['groupID']);

				// Make sure we don't already have an nfo.
				if ($rel['nfostatus'] !== '1') {
					$this->noNFO = true;
				}

				// Go through the nzb for this release looking for a rar, a sample etc...
				foreach ($nzbFiles as $nzbContents) {

					// Check if it's not a nfo, nzb, par2 etc...
					if (preg_match($this->supportFiles . "|nfo\b|inf\b|ofn\b)($|[ \")\]-])(?!.{20,})/i", $nzbContents['title'])) {
						continue;
					}

					// Check if it's a rar/zip.
					if (preg_match("
						/\.(part0*1|part0+|r0+|r0*1|rar|0+|0*10?|zip)(\.rar)*($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i",
						$nzbContents['title']
					)
					) {

						$hasRar = 1;
					}

					// Look for a video sample, make sure it's not an image.
					if ($this->processSample === true &&
						empty($sampleMsgID) &&
						!preg_match('/\.jpe?g/i', $nzbContents['title']) &&
						preg_match('/sample/i', $nzbContents['title'])
					) {

						if (isset($nzbContents['segments'])) {


							// Get the amount of segments for this file.
							$segCount = count($nzbContents['segments']);
							// If it's more than 1 try to get up to the site specified value of segments.
							for ($i = 0; $i < $this->segmentsToDownload; $i++) {
								if ($segCount > $i) {
									$sampleMsgID[] = (string)$nzbContents['segments'][$i];
								} else {
									break;
								}
							}
						}
					}

					// Look for a video file, make sure it's not a sample.
					if ($this->processMediaInfo === true &&
						empty($mediaMsgID) &&
						!preg_match('/sample/i', $nzbContents['title']) &&
						preg_match('/' . $this->videoFileRegex . '[. ")\]]/i', $nzbContents['title'])
					) {

						if (isset($nzbContents['segments'])) {
							$mediaMsgID = (string)$nzbContents['segments'][0];
						}
					}

					// Look for a audio file.
					if ($this->processAudioInfo === true &&
						empty($audioMsgID) &&
						preg_match('/' . $this->audioFileRegex . '[. ")\]]/i', $nzbContents['title'], $type)
					) {

						if (isset($nzbContents['segments'])) {
							// Get the extension.
							$audioType = $type[1];
							$audioMsgID = (string)$nzbContents['segments'][0];
						}
					}

					// Look for a JPG picture, make sure it's not a CD cover.
					if ($this->processJPGSample === true &&
						empty($jpgMsgID) &&
						!preg_match('/flac|lossless|mp3|music|inner-sanctum|sound/i', $groupName) &&
						preg_match('/\.jpe?g[. ")\]]/i', $nzbContents['title'])
					) {

						if (isset($nzbContents['segments'])) {

							$jpgMsgID[] = (string)$nzbContents['segments'][0];
							// If there's more than 1 part, get 2.
							if (count($nzbContents['segments']) > 1) {
								$jpgMsgID[] = (string)$nzbContents['segments'][1];
							}
						}
					}

					// To see if this is book flood.
					if (preg_match($this->ignoreBookRegex, $nzbContents['title'])) {
						$ignoredBooks++;
					}
				}

				// Ignore massive book NZBs.
				$fileCount = count($nzbFiles);
				if ($fileCount > 40 && ($ignoredBooks * 2) >= $fileCount) {
					if (isset($rel['categoryID']) && substr($rel['categoryID'], 0, 1) === '8') {
						$this->db->exec(sprintf('UPDATE releases SET passwordstatus = 0, haspreview = 0, categoryID = 7900 WHERE ID = %d', $rel['ID']));
					}
					$bookFlood = true;
				}

				// Separate the nzb content into the different parts (support files, archive segments and the first parts).
				if ($bookFlood === false && $hasRar !== 0) {
					if ($this->processPasswords === true ||
						$this->processSample === true ||
						$this->processMediaInfo === true ||
						$this->processAudioInfo === true ||
						$this->processVideo === true
					) {

						$this->sum = $this->size = $this->segsize = $this->adj = $notInfinite = $failed = 0;
						$this->name = '';
						$this->ignoreNumbered = false;

						// Loop through the files, attempt to find if password-ed and files. Starting with what not to process.
						foreach ($nzbFiles as $rarFile) {
							if ($this->passChkAttempts > 1) {
								if ($notInfinite > $this->passChkAttempts) {
									break;
								}
							} else {
								if ($notInfinite > $this->partsQTY) {
									if ($this->echooutput) {
										echo "\n";
										echo $this->c->info("Ran out of tries to download yEnc articles for the RAR files.");
									}
									break;
								}
							}

							if ($this->password === true) {
								$this->c->debug('processAdditional',
									'Skipping processing of rar ' . $rarFile['title'] . ' it has a password.'
								);
								break;
							}

							// Probably not a rar/zip.
							if (!preg_match("/\.\b(part\d+|part00\.rar|part01\.rar|rar|r00|r01|zipr\d{2,3}|zip|zipx)($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i", $rarFile['title'])) {
								continue;
							}

							// Process rar contents until 1G or 85% of file size is found (smaller of the two).
							if ($rarFile['size'] === 0 && $rarFile['partsactual'] !== 0 && $rarFile['partstotal'] !== 0) {
								$this->segsize = $rarFile['size'] / ($rarFile['partsactual'] / $rarFile['partstotal']);
							} else {
								$this->segsize = 0;
							}

							$this->sum = $this->sum + $this->adj * $this->segsize;
							if ($this->sum > $this->size || $this->adj === 0) {

								// Get message-id's for the rar file.
								$mID = array_slice((array)$rarFile['segments'], 0, $this->partsQTY);

								// Download the article(s) from usenet.
								$fetchedBinary = $nntp->getMessages($groupName, $mID);
								if ($nntp->isError($fetchedBinary)) {
									$fetchedBinary = false;
								}

								if ($fetchedBinary !== false) {

									// Echo we downloaded rar/zip.
									if ($this->echooutput) {
										echo '(rB)';
									}

									$notInfinite++;

									// Process the rar/zip file.
									$relFiles = $this->processReleaseFiles($fetchedBinary, $rel, $rarFile['title'], $nntp);

									if ($this->password === true) {
										$passStatus[] = Releases::PASSWD_RAR;
									}

									if ($relFiles === false) {
										$this->c->debug('processAdditional', 'Error processing files ' . $rarFile['title']);
										continue;
									}

								} else {

									if ($this->echooutput) {
										echo 'f(' . $notInfinite . ')';
									}

									$notInfinite += 0.2;
									$failed++;
								}
							}
						}
					}

					// Get names of all files in temp dir.
					$files = @scandir($this->tmpPath);
					if ($files !== false) {

						// Loop over them.
						foreach ($files as $file) {

							// Check if the file exists.
							if (is_file($this->tmpPath . $file)) {

								// Check if it's a rar file.
								if (substr($file, -4) === '.rar') {

									// Load the file in archive info.
									$archInfo = new ArchiveInfo();
									$archInfo->open($this->tmpPath . $file, true);
									if ($archInfo->error) {
										continue;
									}

									$tmpFiles = $archInfo->getArchiveFileList();
									if (isset($tmpFiles[0]['name'])) {
										foreach ($tmpFiles as $r) {
											if (isset($r['range'])) {
												$range = $r['range'];
											} else {
												$range = mt_rand(0, 99999);
											}

											$r['range'] = $range;
											if (!isset($r['error'])) {

												if ($rel['categoryID'] !== Category::CAT_MISC_OTHER) {
													// Check if it's a par2.
													if (preg_match('/\.par2/i', $r['name'])) {
														$par2 = $archInfo->getFileData($r['name'], $r['source']);
														// Try to get a release name.
														$this->siftPAR2($par2, $rel);
													}
												}

												if (preg_match(
													$this->supportFiles .
													'|part\d+|r\d{1,3}|zipr\d{2,3}|\d{2,3}|zipx|zip|rar)(\.rar)?$/i', $r['name']
												)
												) {
													continue;
												}

												$this->addFile($r, $rel, $archInfo, $nntp);
											}
										}
									}
								}
							}
						}
					}
				}

				// Check if we should process these types of files.
				if ($this->blnTookSample === false ||
					$this->blnTookAudioinfo === false ||
					$this->blnTookMediainfo === false ||
					$this->blnTookJPG === false ||
					$this->blnTookVideo === false ||
					$this->blnTookAudioSample === false
				) {

					// Get all the names of the files in the temp dir.
					$files = @scandir($this->tmpPath);
					if ($files !== false) {

						// Loop over them.
						foreach ($files as $file) {

							// Check if it's really a file.
							if (is_file($this->tmpPath . $file)) {
								$name = '';

								// Audio sample.
								if (($this->blnTookAudioinfo === false || $this->blnTookAudioSample === false) &&
									preg_match('/(.*)' . $this->audioFileRegex . '$/i', $file, $name)
								) {

									// Move the file.
									@rename($this->tmpPath . $name[0], $this->tmpPath . 'audiofile.' . $name[2]);
									// Try to get audio sample/audio media info.
									$this->getAudioInfo($rel['guid'], $rel['ID'], $name[2]);
									// Delete the file.
									@unlink($this->tmpPath . 'audiofile.' . $name[2]);
								}

								// JGP file sample.
								if ($this->blnTookJPG === false && preg_match('/\.jpe?g$/', $file)) {

									// Try to resize/move the image.
									$this->blnTookJPG =
										$this->releaseImage->saveImage(
											$rel['guid'] . '_thumb',
											$this->tmpPath . $file, $this->jpgSavePath, 650, 650
										);

									// If it's successful, tell the DB.
									if ($this->blnTookJPG !== false) {
										$this->db->exec(sprintf('UPDATE releases SET jpgstatus = %d WHERE ID = %d', 1, $rel['ID']));
									}

									// Delete the old file.
									@unlink($this->tmpPath . $file);
								}

								// Video sample // video clip // video media info.
								if ($this->blnTookSample === false || $this->blnTookVideo === false || $this->blnTookMediainfo === false) {

									// Check if it's a video.
									if (preg_match('/(.*)' . $this->videoFileRegex . '$/i', $file, $name)) {

										// Move it.
										@rename($this->tmpPath . $name[0], $this->tmpPath . 'sample.avi');

										// Try to get a sample with it.
										if ($this->blnTookSample === false) {
											$this->blnTookSample = $this->getSample($rel['guid']);
										}

										// Try to get a video with it. Don't get it here if $sampleMsgID is empty or has 1 message-id (Saves downloading another part).
										if ($this->blnTookVideo === false && count($sampleMsgID) < 2) {
											$this->blnTookVideo = $this->getVideo($rel['guid']);
										}

										// Try to get media info with it.
										if ($this->blnTookMediainfo === false) {
											$this->blnTookMediainfo = $this->getMediaInfo($rel['ID']);
										}

										// Delete it.
										@unlink($this->tmpPath . 'sample.avi');
									}
								}

								// If we got it all, break out.
								if ($this->blnTookJPG === true &&
									$this->blnTookAudioinfo === true &&
									$this->blnTookAudioSample === true &&
									$this->blnTookMediainfo === true &&
									$this->blnTookVideo === true &&
									$this->blnTookSample === true
								) {

									break;
								}
							}
						}
						unset($files);
					}
				}

				// Download and process sample image.
				if ($this->blnTookSample === false || $this->blnTookVideo === false) {

					if (!empty($sampleMsgID)) {

						// Download it from usenet.
						$sampleBinary = $nntp->getMessages($groupName, $sampleMsgID);
						if ($nntp->isError($sampleBinary)) {
							$sampleBinary = false;
						}

						if ($sampleBinary !== false) {
							if ($this->echooutput) {
								echo '(sB)';
							}

							// Check if it's more than 40 bytes.
							if (strlen($sampleBinary) > 40) {

								// Try to create the file.
								$this->addMediaFile($this->tmpPath . 'sample_' . mt_rand(0, 99999) . '.avi', $sampleBinary);

								// Try to get a sample picture.
								if ($this->blnTookSample === false) {
									$this->blnTookSample = $this->getSample($rel['guid']);
								}

								// Try to get a sample video.
								if ($this->blnTookVideo === false) {
									$this->blnTookVideo = $this->getVideo($rel['guid']);
								}

								// Try to get media info. Don't get it here if $mediaMsgID is not empty.
								if ($this->blnTookMediainfo === false && empty($mediaMsgID)) {
									$this->blnTookMediainfo = $this->getMediaInfo($rel['ID']);
								}

							}
							unset($sampleBinary);
						} else {
							if ($this->echooutput) {
								echo 'f';
							}
						}
					}
				}

				// Download and process mediainfo. Also try to get a sample if we didn't get one yet.
				if ($this->blnTookMediainfo === false || $this->blnTookSample === false || $this->blnTookVideo === false) {

					if (!empty($mediaMsgID)) {

						// Try to download it from usenet.
						$mediaBinary = $nntp->getMessages($groupName, $mediaMsgID);
						if ($nntp->isError($mediaBinary)) {
							// If error set it to false.
							$mediaBinary = false;
						}

						if ($mediaBinary !== false) {

							if ($this->echooutput) {
								echo '(mB)';
							}

							// If it's more than 40 bytes...
							if (strlen($mediaBinary) > 40) {

								// Create a file on the disk with it.
								$this->addMediaFile($this->tmpPath . 'media.avi', $mediaBinary);

								// Try to get media info.
								if ($this->blnTookMediainfo === false) {
									$this->blnTookMediainfo = $this->getMediaInfo($rel['ID']);
								}

								// Try to get a sample picture.
								if ($this->blnTookSample === false) {
									$this->blnTookSample = $this->getSample($rel['guid']);
								}

								// Try to get a sample video.
								if ($this->blnTookVideo === false) {
									$this->blnTookVideo = $this->getVideo($rel['guid']);
								}
							}
							unset($mediaBinary);
						} else {
							if ($this->echooutput) {
								echo 'f';
							}
						}
					}
				}

				// Download audio file, use media info to try to get the artist / album.
				if (($this->blnTookAudioinfo === false || $this->blnTookAudioSample === false) && !empty($audioMsgID)) {

					// Try to download it from usenet.
					$audioBinary = $nntp->getMessages($groupName, $audioMsgID);
					if ($nntp->isError($audioBinary)) {
						$audioBinary = false;
					}

					if ($audioBinary !== false) {
						if ($this->echooutput) {
							echo '(aB)';
						}

						// Create a file with it.
						$this->addMediaFile($this->tmpPath . 'audio.' . $audioType, $audioBinary);

						// Try to get media info / sample of the audio file.
						$this->getAudioInfo($rel['guid'], $rel['ID'], $audioType);

						unset($audioBinary);
					} else {
						if ($this->echooutput) {
							echo 'f';
						}
					}
				}

				// Download JPG file.
				if ($this->blnTookJPG === false && !empty($jpgMsgID)) {

					// Try to download it.
					$jpgBinary = $nntp->getMessages($groupName, $jpgMsgID);
					if ($nntp->isError($jpgBinary)) {
						$jpgBinary = false;
					}

					if ($jpgBinary !== false) {

						if ($this->echooutput) {
							echo '(jB)';
						}

						// Try to create a file with it.
						$this->addMediaFile($this->tmpPath . 'samplepicture.jpg', $jpgBinary);

						// Try to resize and move it.
						$this->blnTookJPG = $this->releaseImage->saveImage($rel['guid'] . '_thumb', $this->tmpPath . 'samplepicture.jpg', $this->jpgSavePath, 650, 650);
						if ($this->blnTookJPG !== false) {
							// Update the DB to say we got it.
							$this->db->exec(sprintf('UPDATE releases SET jpgstatus = %d WHERE ID = %d', 1, $rel['ID']));
							if ($this->echooutput) {
								echo 'j';
							}
						}

						@unlink($this->tmpPath . 'samplepicture.jpg');
						unset($jpgBinary);
					} else {
						if ($this->echooutput) {
							echo 'f';
						}
					}
				}

				// Set up release values.
				$hpSQL = $iSQL = $vSQL = $jSQL = '';
				if ($this->processSample === true && $this->blnTookSample !== false) {
					$this->db->exec(sprintf('UPDATE releases SET haspreview = 1 WHERE guid = %s', $this->db->escapeString($rel['guid'])));
				} else {
					$hpSQL = ', haspreview = 0';
				}

				if ($failed > 0) {
					if ($failed / count($nzbFiles) > 0.7 || $notInfinite > $this->passChkAttempts || $notInfinite > $this->partsQTY) {
						$passStatus[] = self::BAD_FILE;
					}
				}

				// If samples exist from previous runs, set flags.
				if (file_exists($this->imgSavePath . $rel['guid'] . '_thumb.jpg')) {
					$iSQL = ', haspreview = 1';
				}
				if (file_exists($this->vidSavePath . $rel['guid'] . '.ogv')) {
					$vSQL = ', videostatus = 1';
				}
				if (file_exists($this->jpgSavePath . $rel['guid'] . '_thumb.jpg')) {
					$jSQL = ', jpgstatus = 1';
				}

				$size = $this->db->queryOneRow('SELECT COUNT(releasefiles.releaseID) AS count, SUM(releasefiles.size) AS size FROM releasefiles WHERE releaseID = ' . $rel['ID']);

				$pStatus = max($passStatus);
				if ($this->processPasswords === true && $pStatus > 0) {
					$sql = sprintf('UPDATE releases SET passwordstatus = %d, rarinnerfilecount = %d %s %s %s %s WHERE ID = %d', $pStatus, $size['count'], $iSQL, $vSQL, $jSQL, $hpSQL, $rel['ID']);
				} else if ($hasRar && ((isset($size['size']) && (is_null($size['size']) || $size['size'] === '0')) || !isset($size['size']))) {
					if (!$this->blnTookSample) {
						$hpSQL = '';
					}
					$sql = sprintf('UPDATE releases SET passwordstatus = passwordstatus - 1, rarinnerfilecount = %d %s %s %s %s WHERE ID = %d', $size['count'], $iSQL, $vSQL, $jSQL, $hpSQL, $rel['ID']);
				} else {
					$sql = sprintf('UPDATE releases SET passwordstatus = %s, rarinnerfilecount = %d %s %s %s %s WHERE ID = %d', Releases::PASSWD_NONE, $size['count'], $iSQL, $vSQL, $jSQL, $hpSQL, $rel['ID']);
				}

				$this->db->exec($sql);

				// Erase all files and directory.
				foreach (glob($this->tmpPath . '*') as $v) {
					@unlink($v);
				}
				foreach (glob($this->tmpPath . '.*') as $v) {
					@unlink($v);
				}
				@rmdir($this->tmpPath);
			}
			if ($this->echooutput) {
				echo "\n";
			}
		}

		unset($rar, $nzbContents);
	}

	/**
	 * Convert bytes to kb/mb/gb/tb and return in human readable format.
	 *
	 * @param int $bytes
	 *
	 * @return string
	 */
	protected function readableBytesString($bytes)
	{
		$kb = 1024;
		$mb = $kb * $kb;
		$gb = $kb * $mb;
		$tb = $kb * $gb;
		if ($bytes < $kb) {
			return $bytes . 'B';
		} else if ($bytes < ($mb)) {
			return round($bytes / $kb, 1) . 'KB';
		} else if ($bytes < $gb) {
			return round($bytes / $mb, 1) . 'MB';
		} else if ($bytes < $tb) {
			return round($bytes / $gb, 1) . 'GB';
		} else {
			return round($bytes / $tb, 1) . 'TB';
		}
	}

	/**
	 * Comparison function for uSort, for sorting nzb file content.
	 *
	 * @note used in processAdditional
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return int
	 */
	protected function sortRAR($a, $b)
	{
		$pos = 0;
		$af = $bf = false;
		$a = preg_replace('/\d+[- ._]?(\/|\||[o0]f)[- ._]?\d+?(?![- ._]\d)/i', ' ', $a['title']);
		$b = preg_replace('/\d+[- ._]?(\/|\||[o0]f)[- ._]?\d+?(?![- ._]\d)/i', ' ', $b['title']);

		if (preg_match("/\.(part\d+|r\d+)(\.rar)*($|[ \")\]-])/i", $a)) {
			$af = true;
		}
		if (preg_match("/\.(part\d+|r\d+)(\.rar)*($|[ \")\]-])/i", $b)) {
			$bf = true;
		}

		if (!$af && preg_match("/\.(rar)($|[ \")\]-])/i", $a)) {
			$a = preg_replace('/\.(rar)(?:$|[ \")\]-])/i', '.*rar', $a);
			$af = true;
		}
		if (!$bf && preg_match("/\.(rar)($|[ \")\]-])/i", $b)) {
			$b = preg_replace('/\.(rar)(?:$|[ \")\]-])/i', '.*rar', $b);
			$bf = true;
		}

		if (!$af && !$bf) {
			return strnatcasecmp($a, $b);
		} else if (!$bf) {
			return -1;
		} else if (!$af) {
			return 1;
		}

		if ($af && $bf) {
			$pos = strnatcasecmp($a, $b);
		} else if ($af) {
			$pos = -1;
		} else if ($bf) {
			$pos = 1;
		}

		return $pos;
	}

	/**
	 * @note Called by addFile, getRar, processAdditional
	 *
	 * @param $file
	 * @param $data
	 *
	 * @return void
	 */
	protected function addMediaFile($file, $data)
	{
		if (@file_put_contents($file, $data) !== false) {
			$xmlArray = $this->functions->runCmd('"' . $this->site->mediainfopath . '" --Output=XML "' . $file . '"');
			if (is_array($xmlArray)) {
				$xmlArray = implode("\n", $xmlArray);
				$xmlObj = @simplexml_load_string($xmlArray);
				$arrXml = $this->functions->objectsIntoArray($xmlObj);
				if (!isset($arrXml['File']['track'][0])) {
					@unlink($file);
				}
			}
		}
	}

	/**
	 * @note Called by processAdditional, processReleaseFiles, processReleaseZips.
	 *
	 * @param      $v
	 * @param      $release
	 * @param bool |ArchiveInfo $rar
	 * @param      $nntp
	 *
	 * @return void
	 */
	protected function addFile($v, $release, $rar = false, $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(PProcess->addFile).\n"));
		}

		if (!isset($v['error']) && isset($v['source'])) {
			if ($rar !== false && preg_match('/\.zip$/', $v['source'])) {
				$zip = new ZipInfo();
				$tmpData = $zip->getFileData($v['name'], $v['source']);
			} else if ($rar !== false) {
				$tmpData = $rar->getFileData($v['name'], $v['source']);
			} else {
				$tmpData = false;
			}

			// Check if we already have the file or not.
			// Also make sure we don't add too many files, some releases have 100's of files, like PS3 releases.
			if ($this->filesAdded < 11 && $this->db->queryOneRow(sprintf('SELECT ID FROM releasefiles WHERE releaseID = %d AND name = %s AND size = %d', $release['ID'], $this->db->escapeString($v['name']), $v['size'])) === false) {
				if ($this->releaseFiles->add($release['ID'], $v['name'], $v['size'], $v['date'], $v['pass'])) {
					$this->filesAdded++;
					$this->newfiles = true;
					if ($this->echooutput) {
						echo '^';
					}
				}
			}

			if ($tmpData !== false) {
				// Extract a NFO from the rar.
				if ($this->noNFO === true && $v['size'] > 100 && $v['size'] < 100000 && preg_match('/(\.(nfo|inf|ofn)|info.txt)$/i', $v['name'])) {
					if ($this->Nfo->addAlternateNfo($tmpData, $release, $nntp)) {
						$this->c->debug('addFile', 'Added NFO from RAR for releaseID ' . $release['ID']);
						if ($this->echooutput)
							echo 'n';
						$this->noNFO = false;
					}
				} // Extract a video file from the compressed file.
				else if ($this->site->mediainfopath !== '' && $this->processVideo === true && preg_match('/' . $this->videoFileRegex . '$/i', $v['name']))
					$this->addMediaFile($this->tmpPath . 'sample_' . mt_rand(0, 99999) . '.avi', $tmpData);
				// Extract an audio file from the compressed file.
				else if ($this->site->mediainfopath !== '' && preg_match('/' . $this->audioFileRegex . '$/i', $v['name'], $ext))
					$this->addMediaFile($this->tmpPath . 'audio_' . mt_rand(0, 99999) . $ext[0], $tmpData);
				else if ($this->site->mediainfopath !== '' && preg_match('/([^\/\\\r]+)(\.[a-z][a-z0-9]{2,3})$/i', $v['name'], $name))
					$this->addMediaFile($this->tmpPath . $name[1] . mt_rand(0, 99999) . $name[2], $tmpData);
			}
			unset($tmpData, $rf);
		}
	}

	/**
	 * Open the zip, see if it has a password, attempt to get a file.
	 *
	 * @note Called by processReleaseFiles
	 *
	 * @param      $fetchedBinary
	 * @param bool $open
	 * @param bool $data
	 * @param      $release
	 * @param      $nntp
	 *
	 * @return array|bool
	 */
	protected function processReleaseZips($fetchedBinary, $open = false, $data = false, $release, $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Not connected to usenet(PProcess->processReleaseZips).\n"));
		}

		// Load the ZIP file or data.
		$zip = new ZipInfo();
		if ($open)
			$zip->open($fetchedBinary, true);
		else
			$zip->setData($fetchedBinary, true);

		if ($zip->error) {
			$this->c->debug('processReleaseZips', 'ZIP Error: ' . $zip->error);

			return false;
		}

		if (!empty($zip->isEncrypted)) {
			$this->c->debug('processReleaseZips', 'ZIP archive is password encrypted for release ' . $release['ID']);
			$this->password = true;

			return false;
		}

		$files = $zip->getFileList();
		$dataArray = array();
		if ($files !== false) {

			if ($this->echooutput) {
				echo 'z';
			}
			$limit = 0;
			foreach ($files as $file) {
				$thisData = $zip->getFileData($file['name']);
				$dataArray[] = array('zip' => $file, 'data' => $thisData);

				// Process RARs inside the ZIP.
				if (preg_match('/\.(r\d+|part\d+|rar)$/i', $file['name']) || preg_match('/\bRAR\b/i', $thisData)) {

					$tmpFiles = $this->getRar($thisData);
					if ($tmpFiles !== false) {

						foreach ($tmpFiles as $f) {

							if ($limit++ > 11) {
								break;
							}
							$this->addFile($f, $release, false, $nntp);
							$files[] = $f;
						}
					}
				} //Extract a NFO from the zip.
				else if ($this->noNFO === true && $file['size'] < 100000 && preg_match('/\.(nfo|inf|ofn)$/i', $file['name'])) {
					if ($file['compressed'] !== 1) {
						if ($this->Nfo->addAlternateNfo($thisData, $release, $nntp)) {
							$this->c->debug('processReleaseZips', 'Added NFO from ZIP file for releaseID ' . $release['ID']);
							if ($this->echooutput) {
								echo 'n';
							}
							$this->noNFO = false;
						}
					} else if ($this->site->zippath !== '' && $file['compressed'] === 1) {

						$zip->setExternalClient($this->site->zippath);
						$zipData = $zip->extractFile($file['name']);
						if ($zipData !== false && strlen($zipData) > 5) {
							if ($this->Nfo->addAlternateNfo($zipData, $release, $nntp)) {

								$this->c->debug('processReleaseZips', 'Added compressed NFO from ZIP file for releaseID ' . $release['ID']);
								if ($this->echooutput) {
									echo 'n';
								}

								$this->noNFO = false;
							}
						}
					}
				}
			}
		}

		if ($data) {
			$files = $dataArray;
			unset($dataArray);
		}

		unset($fetchedBinary, $zip);

		return $files;
	}

	/**
	 * Get contents of rar file.
	 *
	 * @note Called by processReleaseFiles and processReleaseZips
	 *
	 * @param $fetchedBinary
	 *
	 * @return array|bool
	 */
	protected function getRar($fetchedBinary)
	{
		$rar = new ArchiveInfo();
		$files = $retVal = false;
		if ($rar->setData($fetchedBinary, true)) {
			// Useless?
			$files = $rar->getArchiveFileList();
		}
		if ($rar->error) {
			$this->c->debug('getRar', 'RAR Error: ' . $rar->error);

			return $retVal;
		}
		if (!empty($rar->isEncrypted)) {
			$this->c->debug('getRar', 'Archive is password encrypted.');
			$this->password = true;

			return $retVal;
		}
		$tmp = $rar->getSummary(true, false);

		if (isset($tmp['is_encrypted']) && $tmp['is_encrypted'] != 0) {
			$this->c->debug('getRar', 'Archive is password encrypted.');
			$this->password = true;

			return $retVal;
		}
		$files = $rar->getArchiveFileList();
		if ($files !== false) {
			$retVal = array();
			if ($this->echooutput !== false) {
				echo 'r';
			}
			foreach ($files as $file) {
				if (isset($file['name'])) {
					if (isset($file['error'])) {
						$this->c->debug('getRar', "Error: {$file['error']} (in: {$file['source']})");
						continue;
					}
					if (isset($file['pass']) && $file['pass'] == true) {
						$this->password = true;
						break;
					}
					if (preg_match($this->supportFiles . ')(?!.{20,})/i', $file['name'])) {
						continue;
					}
					if (preg_match('/([^\/\\\\]+)(\.[a-z][a-z0-9]{2,3})$/i', $file['name'], $name)) {
						$rarFile = $this->tmpPath . $name[1] . mt_rand(0, 99999) . $name[2];
						$fetchedBinary = $rar->getFileData($file['name'], $file['source']);
						if ($this->site->mediainfopath !== '') {
							$this->addMediaFile($rarFile, $fetchedBinary);
						}
					}
					if (!preg_match('/\.(r\d+|part\d+)$/i', $file['name'])) {
						$retVal[] = $file;
					}
				}
			}
		}

		if (count($retVal) === 0)
			return false;

		return $retVal;
	}

	/**
	 * Open the rar, see if it has a password, attempt to get a file.
	 *
	 * @note Only called by processAddtional
	 *
	 * @param $fetchedBinary
	 * @param $release
	 * @param $name
	 * @param $nntp
	 *
	 * @return array|bool
	 */
	protected function processReleaseFiles($fetchedBinary, $release, $name, $nntp)
	{
		if (!isset($nntp)) {
			// TODO: This should be an exception.
			exit($this->c->error("Not connected to usenet(postprocess->processReleaseFiles).\n"));
		}

		$retVal = array();
		$rar = new ArchiveInfo();
		$this->password = false;

		if (preg_match("/\.(part\d+|rar|r\d{1,3})($|[ \")\]-])|\"[a-f0-9]{32}\.[1-9]\d{1,2}\".*\(\d+\/\d{2,}\)$/i", $name)) {
			// Give the data to archiveinfo so it can check if it's a rar.
			if ($rar->setData($fetchedBinary, true) === false) {
				return false;
			}

			if ($rar->error) {
				$this->c->debug('processReleaseFiles', "Error: {$rar->error}.");
				return false;
			}

			$tmp = $rar->getSummary(true, false);
			if (preg_match('/par2/i', $tmp['main_info']))
				return false;

			if (isset($tmp['is_encrypted']) && $tmp['is_encrypted'] != 0) {
				$this->c->debug('processReleaseFiles', 'Archive is password encrypted.');
				$this->password = true;
				return false;
			}

			if (!empty($rar->isEncrypted)) {
				$this->c->debug('processReleaseFiles', 'Archive is password encrypted.');
				$this->password = true;

				return false;
			}

			$files = $rar->getArchiveFileList();
			if (count($files) === 0 || !is_array($files) || !isset($files[0]['compressed'])) {
				return false;
			}

			if ($files[0]['compressed'] == 0 && $files[0]['name'] != $this->name) {
				$this->name = $files[0]['name'];
				$this->size = $files[0]['size'] * 0.95;
				$this->adj = $this->sum = 0;

				if ($this->echooutput) {
					echo 'r';
				}
				// If archive is not stored compressed, process data
				foreach ($files as $file) {
					if (isset($file['name'])) {
						if (isset($file['error'])) {
							$this->c->debug('processReleaseFiles', "Error: {$file['error']} (in: {$file['source']})");
							continue;
						}
						if ($file['pass'] == true) {
							$this->password = true;
							break;
						}

						if ($release['categoryID'] == Category::CAT_MISC_OTHER) {
							// Check if it's a par2.
							if (preg_match('/\.par2/i', $file['name'])) {
								$par2 = $rar->getFileData($file['name'], $file['source']);
								// Try to get a release name.
								$this->siftPAR2($par2, $release);
							}
						}

						if (preg_match($this->supportFiles . ')(?!.{20,})/i', $file['name'])) {
							continue;
						}

						if (preg_match('/\.zip$/i', $file['name'])) {
							$this->processReleaseZips($rar->getFileData($file['name'], $file['source']), false, true, $release, $nntp);
						}

						if (!isset($file['next_offset'])) {
							$file['next_offset'] = 0;
						}
						$range = mt_rand(0, 99999);
							if (isset($file['range'])) {
								$range = $file['range'];
						}
						$retVal[] = array('name' => $file['name'], 'source' => $file['source'], 'range' => $range, 'size' => $file['size'], 'date' => $file['date'], 'pass' => $file['pass'], 'next_offset' => $file['next_offset']);
						$this->adj = $file['next_offset'] + $this->adj;
					}
				}

				$this->sum = $this->adj;
						if ($this->segsize !== 0) {
							$this->adj = $this->adj / $this->segsize;
						} else {
							$this->adj = 0;
						}
						if ($this->adj < .7) {
							$this->adj = 1;
						}
					} else {
						$this->size = $files[0]['size'] * 0.95;
				if ($this->name != $files[0]['name']) {
					$this->name = $files[0]['name'];
					$this->sum = $this->segsize;
					$this->adj = 1;
				}

				// File is compressed, use unrar to get the content
				$rarFile = $this->tmpPath . 'rarfile' . mt_rand(0, 99999) . '.rar';
				if (@file_put_contents($rarFile, $fetchedBinary)) {
					$execString = '"' . $this->site->unrarpath . '" e -ai -ep -c- -id -inul -kb -or -p- -r -y "' . $rarFile . '" "' . $this->tmpPath . '"';
					$this->functions->runCmd($execString);
					if (isset($files[0]['name'])) {
						if ($this->echooutput) {
							echo 'r';
						}
						foreach ($files as $file) {
							if (isset($file['name'])) {
								if (!isset($file['next_offset'])) {
									$file['next_offset'] = 0;
								}
								$range = mt_rand(0, 99999);
								if (isset($file['range'])) {
									$range = $file['range'];
								}

								$retVal[] = array('name' => $file['name'], 'source' => $file['source'], 'range' => $range, 'size' => $file['size'], 'date' => $file['date'], 'pass' => $file['pass'], 'next_offset' => $file['next_offset']);
							}
						}
					}
				}
			}
		}
		 else {
			// Not a rar file, try it as a ZIP file.
			$files = $this->processReleaseZips($fetchedBinary, false, false, $release, $nntp);
			if ($files !== false && isset($files[0]['name'])) {
				$this->name = $files[0]['name'];
				$this->size = $files[0]['size'] * 0.95;
				$this->sum = $this->adj = 0;

				foreach ($files as $file) {
					if (isset($file['pass']) && $file['pass']) {
						$this->password = true;
						break;
					}

					if (!isset($file['next_offset'])) {
						$file['next_offset'] = 0;
					}
					if (!isset($file['range'])) {
						$file['range'] = 0;
					}

					$retVal[] = array('name' => $file['name'], 'source ' => 'main', 'range' => $file['range'], 'size' => $file['size'], 'date' => $file['date'], 'pass' => $file['pass'], 'next_offset' => $file['next_offset']);
					$this->adj = $file['next_offset'] + $this->adj;
					$this->sum = $file['size'] + $this->sum;
				}

				$this->size = $this->sum;
				$this->sum = $this->adj;
				if ($this->segsize !== 0) {
					$this->adj = $this->adj / $this->segsize;
				} else {
					$this->adj = 0;
				}

				if ($this->adj < .7) {
					$this->adj = 1;
				}
			} // Not a compressed file, but segmented.
			else {
				$this->ignoreNumbered = true;
			}
		}

		// Use found content to populate release files, nfo, and create multimedia files.
		foreach ($retVal as $k => $v) {
			if (!preg_match($this->supportFiles . '|part\d+|r\d{1,3}|zipr\d{2,3}|\d{2,3}|zipx|zip|rar)(\.rar)?$/i', $v['name']) && count($retVal) > 0) {
				$this->addFile($v, $release, $rar, $nntp);
			} else {
				unset($retVal[$k]);
			}
		}

		if (count($retVal) === 0) {
			$retVal = false;
		}
		unset($fetchedBinary, $rar, $nfo);
		return $retVal;
	}

	/**
	 * Go through PAR2 data find a releasename.
	 *
	 * @param string $PAR2    PAR2 binary data.
	 * @param array  $release Row from DB with release info.
	 *
	 * @return bool
	 * @access protected
	 */
	protected function siftPAR2($PAR2, $release)
	{
		// Run it through namefixer.
		$release['textstring'] = $PAR2;
		$release['releaseID'] = $release['ID'];
		if ($this->nameFixer->tvCheck($release, 1, 'PAR2, ', 1, 1) !== true) {
			return false;
		}

		return true;
	}

	/**
	 * Attempt to get media info xml from a video file.
	 *
	 * @note Only called by processAdditional
	 *
	 * @param $releaseID
	 *
	 * @return bool
	 * @access protected
	 */
	protected function getMediaInfo($releaseID)
	{
		// Return value.
		$retVal = false;

		if (!$this->processMediaInfo) {
			return $retVal;
		}

		// Get all the files in the temp folder.
		$mediaFiles = glob($this->tmpPath . '*.*');

		// Check if we got them.
		if ($mediaFiles !== false) {

			// Loop over them.
			foreach ($mediaFiles as $mediaFile) {

				// Look for the video file.
				if (preg_match('/\.avi$/i', $mediaFile) && is_file($mediaFile)) {

					// Run media info on it.
					$xmlArray = $this->functions->runCmd('"' . $this->site->mediainfopath . '" --Output=XML "' . $mediaFile . '"');

					// Check if we got it.
					if (is_array($xmlArray)) {

						// Convert it to string.
						$xmlArray = implode("\n", $xmlArray);

						// Insert it into the DB.
						$this->releaseExtra->addFull($releaseID, $xmlArray);
						$this->releaseExtra->addFromXml($releaseID, $xmlArray);

						$retVal = true;
						if ($this->echooutput) {
							echo 'm';
						}
						break;
					}
				}
			}
		}

		return $retVal;
	}

	/**
	 * Attempt to get media info/sample/title from a audio file.
	 *
	 * @note Only called by processAdditional
	 *
	 * @param        $releaseGUID
	 * @param        $releaseID
	 * @param string $extension , the extension (mp3, FLAC, etc).
	 *
	 * @return bool
	 */
	protected function getAudioInfo($releaseGUID, $releaseID, $extension)
	{
		// Return values.
		$retVal = $audVal = false;

		// Check if audio sample fetching is on.
		if (!$this->processAudioSample) {
			$audVal = true;
		}

		// Check if media info fetching is on.
		if (!$this->processAudioInfo) {
			$retVal = true;
		}

		$category = new Category();
		$musicParent = (string)Category::CAT_PARENT_MUSIC;
		// Make sure the category is music or other->misc.
		$rQuery = $this->db->queryOneRow(
			sprintf(
				'SELECT searchname, categoryID as ID, groupID FROM releases WHERE proc_pp = 0 AND ID = %d', $releaseID
			)
		);
		if (!preg_match(
			'/^' .
			$musicParent[0] .
			'\d{3}|' .
			Category::CAT_MISC_OTHER .
			'|' .
			Category::CAT_MOVIE_OTHER .
			'|' .
			Category::CAT_TV_OTHER .
			'/',
			$rQuery['ID']
		)
		) {

			return false;
		}

		// Get all the files in temp folder.
		$audioFiles = glob($this->tmpPath . '*.*');

		// Check that we got some files.
		if ($audioFiles !== false) {

			// Loop over the files.
			foreach ($audioFiles as $audioFile) {

				// Check if we find the file.
				if (preg_match('/' . $extension . '$/i', $audioFile) && is_file($audioFile)) {

					// Check if media info is enabled.
					if ($retVal === false) {

						//  Get the media info for the file.
						$xmlArray = $this -> functions->runCmd('"' . $this->site->mediainfopath . '" --Output=XML "' . $audioFile . '"');
						if (is_array($xmlArray)) {

							// Convert to array.
							$arrXml = $this->functions->objectsIntoArray(@simplexml_load_string(implode("\n", $xmlArray)));


							if (isset($arrXml['File']['track'])) {

								foreach ($arrXml['File']['track'] as $track) {

									if (isset($track['Album']) && isset($track['Performer'])) {

										// Make the extension upper case.
										$ext = strtoupper($extension);

										// Form a new search name.
										if (!empty($track['Recorded_date']) && preg_match('/(?:19|20)\d\d/', $track['Recorded_date'], $Year)) {
											$newName = $track['Performer'] . ' - ' . $track['Album'] . ' (' . $Year[0] . ') ' . $ext;
										} else {
											$newName = $track['Performer'] . ' - ' . $track['Album'] . ' ' . $ext;
										}

										// Get the category or try to determine it.
										if ($ext === 'MP3') {
											$newCat = Category::CAT_MUSIC_MP3;
										} else if ($ext === 'FLAC') {
											$newCat = Category::CAT_MUSIC_LOSSLESS;
										} else {
											$newCat = $category->determineCategory($rQuery['groupID'], $newName);
										}

										// Update the search name.
										$this->db->exec(sprintf('UPDATE releases SET searchname = %s, categoryID = %d, iscategorized = 1, isrenamed = 1, proc_pp = 1 WHERE ID = %d', $this->db->escapeString(substr($newName, 0, 255)), $newCat, $releaseID));

										$this->c->debug(
											'getAudioInfo',
											"New name:(" . $newName .
											") Old name:(" . $rQuery["searchname"] .
											") New cat:(" . $newCat .
											") Old cat:(" . $rQuery['ID'] .
											") Group:(" . $rQuery['groupID'] .
											") Method:(" . 'PostProccess getAudioInfo' .
											") ReleaseID:(" . $releaseID . ')'
											, 5
										);

										// Add the media info.
										$this->releaseExtra->addFromXml($releaseID, $xmlArray);

										$retVal = true;
										$this->blnTookAudioinfo = true;
										if ($this->echooutput) {
											echo 'a';
										}
										break;
									}
								}
							}
						}
					}

					// Check if creating audio samples is enabled.
					if ($audVal === false) {

						// File name to store audio file.
						$audioFileName = $releaseGUID . '.ogg';

						// Create an audio sample.
						$this->functions->runCmd(
							'"' .
							$this->site->ffmpegpath .
							'" -t 30 -i "' .
							$audioFile .
							'" -acodec libvorbis -loglevel quiet -y "' .
							$this->tmpPath .
							$audioFileName .
							'"'
						);

						// Get all the files in the temp path.
						$all_files = @scandir($this->tmpPath, 1);

						// If it's false, continue.
						if ($all_files === false) {
							continue;
						}

						// Loop over the temp files.
						foreach ($all_files as $file) {

							// Try to find the temp audio file.
							if ($file === $audioFileName) {

								// Try to move the temp audio file.
								$renamed = @rename($this->tmpPath . $audioFileName, $this->audSavePath . $audioFileName);

								if (!$renamed) {
									// Try to copy it if it fails.
									$copied = @copy($this->tmpPath . $audioFileName, $this->audSavePath . $audioFileName);

									// Delete the old file.
									@unlink($this->tmpPath . $audioFileName);

									// If it didn't copy continue.
									if (!$copied) {
										continue;
									}
								}

								// Try to set the file perms.
								@chmod($this->audSavePath . $audioFileName, 0764);

								// Update DB to said we got a audio sample.
								$this->db->exec(sprintf('UPDATE releases SET audiostatus = 1 WHERE ID = %d', $releaseID));

								$audVal = true;
								$this->blnTookAudioSample = true;

								if ($this->echooutput) {
									echo 'A';
								}

								break;
							}
						}
					}
					// If we got both, break.
					if ($retVal === true && $audVal === true) {
						break;
					}
				}
			}
		}

		return ($retVal && $audVal);
	}

	/**
	 * Attempt to get a sample image from a video file.
	 *
	 * @note Only called by processAdditional
	 *
	 * @param string $releaseGUID
	 *
	 * @return bool
	 */
	protected function getSample($releaseGUID)
	{
		// Return value.
		$retVal = false;

		if (!$this->processSample) {
			return $retVal;
		}

		// Get all file in temp folder.
		$sampleFiles = glob($this->tmpPath . '*.*');

		// Check if it failed.
		if ($sampleFiles !== false) {

			// Create path to temp file.
			$fileName = 'zzzz' . mt_rand(5, 12) . mt_rand(5, 12) . '.jpg';

			// Loop over all the files.
			foreach ($sampleFiles as $sampleFile) {

				// Look for a file ending with .avi, check if it's really a file.
				if (preg_match('/\.avi$/i', $sampleFile) && is_file($sampleFile)) {

					// Get the exact time of this video.
					$time = @exec(
						'"' .
						$this->site->ffmpegpath .
						'" -i "' .
						$sampleFile .
						'" -vcodec copy -f null /dev/null 2>&1 | cut -f 6 -d \'=\' | grep \'^[0-9].*bitrate\' | cut -f 1 -d \' \''
					);

					// If it's 11 chars long, it's good (00:00:00.00)
					if (strlen($time) !== 11) {
						// If not set it to 1 second.
						$time = '00:00:01';
					}

					// Create the image.
					$this->functions->runCmd(
						'"' .
						$this->site->ffmpegpath .
						'" -i "' .
						$sampleFile .
						'" -ss ' .
						$time .
						' -loglevel quiet -vframes 1 -y "' .
						$this->tmpPath .
						$fileName .
						'"'
					);

					// Get all the files in the temp folder.
					$all_files = @scandir($this->tmpPath, 1);

					// Loop all the files.
					foreach ($all_files as $file) {

						// Check if the file is the file we created.
						if ($file === $fileName) {

							// Try to resize/move the image.
							$saved = $this->releaseImage->saveImage(
								$releaseGUID . '_thumb',
								$this->tmpPath . $file,
								$this->releaseImage->imgSavePath, 800, 600
							);

							// Delete the temp file we created.
							@unlink($this->tmpPath . $fileName);

							// Check if it saved.
							if ($saved === 1) {

								$retVal = true;
								if ($this->echooutput) {
									echo 's';
								}

								return $retVal;
							}
						}
					}
				}
			}
		}

		// If an image was made, return true, else return false.
		return $retVal;
	}

	/**
	 * Get a video sample.
	 *
	 * @note Only called by processAdditional
	 *
	 * @param $releaseGUID GUID of the release.
	 *
	 * @return bool
	 */
	protected function getVideo($releaseGUID)
	{
		// Return value.
		$retVal = false;

		if (!$this->processVideo) {
			return $retVal;
		}

		// Get all the files in the temp dir.
		$sampleFiles = glob($this->tmpPath . '*.*');
		if ($sampleFiles !== false) {

			// Create a filename to store the temp file.
			$fileName = 'zzzz' . $releaseGUID . '.ogv';

			// Loop all the files in the temp folder.
			foreach ($sampleFiles as $sampleFile) {

				// Try to find an avi file.
				if (preg_match('/\.avi$/i', $sampleFile) && is_file($sampleFile)) {

					// If wanted sample length is less than 60, try to get sample from the end of the video.
					if ($this->ffMPEGDuration < 60) {
						// Get the real duration of the file.
						$time = @exec(
							'"' .
							$this->site->ffmpegpath .
							'" -i "' .
							$sampleFile .
							'" -vcodec copy -f null /dev/null 2>&1 | cut -f 6 -d \'=\' | grep \'^[0-9].*bitrate\' | cut -f 1 -d \' \''
						);

						// If we don't get the time create the sample the old way (gets the start of the video).
						$numbers = array();
						if (!preg_match('/^\d{2}:\d{2}:(\d{2}).(\d{2})$/', $time, $numbers)) {
							$this->functions->runCmd(
								'"' .
								$this->site->ffmpegpath .
								'" -i "' .
								$sampleFile .
								'" -vcodec libtheora -filter:v scale=320:-1 -t ' .
								$this->ffMPEGDuration .
								' -acodec libvorbis -loglevel quiet -y "' .
								$this->tmpPath .
								$fileName .
								'"'
							);
						} else {
							// Get the max seconds from the video clip.
							$maxLength = (int)$numbers[1];

							// If the clip is shorter than the length we want.
							if ($maxLength <= $this->ffMPEGDuration) {
								// The lowest we want is 0.
								$lowestLength = '00:00:00.00';

								// If it's longer.
							} else {
								// The lowest we want is the the difference .
								$lowestLength = ($maxLength - $this->ffMPEGDuration);

								// Form the time string.
								$end = '.' . $numbers[2];
								switch (strlen($lowestLength)) {
									case 1:
										$lowestLength = '00:00:0' . (string)$lowestLength . $end;
										break;
									case 2:

										$lowestLength = '00:00:' . (string)$lowestLength . $end;
										break;
									default:
										$lowestLength = '00:00:60.00';
								}
							}

							// Try to get the sample (from the end instead of the start).
							$this->functions->runCmd(
								'"' .
								$this->site->ffmpegpath .
								'" -i "' .
								$sampleFile .
								'" -ss ' . $lowestLength .
								' -t ' . $this->ffMPEGDuration .
								' -vcodec libtheora -filter:v scale=320:-1 ' .
								' -acodec libvorbis -loglevel quiet -y "' .
								$this->tmpPath .
								$fileName .
								'"'
							);
						}
					} else {
						// If longer than 60, then run the old way.
						$this->functions->runCmd(
							'"' .
							$this->site->ffmpegpath .
							'" -i "' .
							$sampleFile .
							'" -vcodec libtheora -filter:v scale=320:-1 -t ' .
							$this->ffMPEGDuration .
							' -acodec libvorbis -loglevel quiet -y "' .
							$this->tmpPath .
							$fileName .
							'"'
						);
					}

					// Get all the files in the temp dir.
					$all_files = @scandir($this->tmpPath, 1);
					if ($all_files === false) {
						continue;
					}

					// Loop over them.
					foreach ($all_files as $file) {

						// Until we find the video file.
						if ($file === $fileName) {

							// Create a path to where the file should be moved.
							$newFile = $this->vidSavePath . $releaseGUID . '.ogv';

							// Try to move the file to the new path.
							$renamed = @rename($this->tmpPath . $fileName, $newFile);

							// If we couldn't rename it, try to copy it.
							if (!$renamed) {

								$copied = @copy($this->tmpPath . $fileName, $newFile);

								// Delete the old file.
								@unlink($this->tmpPath . $fileName);

								// If it didn't copy, continue.
								if (!$copied) {
									continue;
								}
							}

							// Change the permissions.
							@chmod($newFile, 0764);

							// Update query to say we got the video.
							$this->db->exec(sprintf('UPDATE releases SET videostatus = 1 WHERE guid = %s', $this->db->escapeString($releaseGUID)));
							$retVal = true;
							if ($this->echooutput) {
								echo 'v';
							}

							return $retVal;
						}
					}
				}
			}
		}

		// If an video was made, return true, else return false.
		return $retVal;
	}

}
