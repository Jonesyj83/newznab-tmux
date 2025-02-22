<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BinaryblacklistTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        DB::table('binaryblacklist')->delete();

        DB::table('binaryblacklist')->insert([
            0 => [
                'id' => 1,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => '(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)[\\)]?( \\-)?[ \\-\\.]((19|20)\\d\\d|(480|720|1080)(i|p)|3d|5\\.1|dts|ac3|truehd|(bd|dvd|hd|sat|vhs|web)\\.?rip|(bd.)?(h|x).?2?64|divx|xvid|bluray|svcd|board|custom|"|(d|h|p|s)d?v?tv|m?dvd(-|sc)?r|int(ernal)?|nzb|par2|\\b(((dc|ld|md|ml|dl|hr|se)[.])|(anime\\.)|(fs|ws)|dsr|pal|ntsc|iso|complete|cracked|ebook|extended|dirfix|festival|proper|game|limited|read.?nfo|real|rerip|repack|remastered|retail|samplefix|scan|screener|theatrical|uncut|unrated|incl|winall)\\b|doku|doc|dub|sub|\\(uncut\\))',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blacklists non-english releases.',
                'last_activity' => null,
            ],
            1 => [
                'id' => 2,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => '[ -.](bl|cz|de|es|fr|ger|heb|hu|hun|ita|ko|kor|nl|pl|se)[ -.]((19|20)\\d\\d|(480|720|1080)(i|p)|(bd|dvd.?|sat|vhs)?rip?|(bd|dl)mux|( -.)?(dub|sub)(ed|bed)?|complete|convert|(d|h|p|s)d?tv|dirfix|docu|dual|dvbs|dvdscr|eng|(h|x).?2?64|int(ernal)?|pal|proper|repack|xbox)',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blacklists non-english abbreviated releases.',
                'last_activity' => null,
            ],
            2 => [
                'id' => 3,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => '[ -.]((19|20)\\d\\d|(bd|dvd.?|sat|vhs)?rip?|custom|divx|dts)[ -.](bl|cz|de|es|fr|ger|heb|hu|ita|ko|kor|nl|pl|se)[ -.]',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blacklists non-english abbreviated (reversed) releases.',
                'last_activity' => null,
            ],
            3 => [
                'id' => 4,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => '[ -.](chinese.subbed|dksubs|fansubs?|finsub|hebdub|hebsub|korsub|norsub|nordicsubs|nl( -.)?sub(ed|bed|s)?|nlvlaams|pldub|plsub|slosinh|swesub|truefrench|vost(fr)?)[ -.]',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blacklists non-english subtitled releases.',
                'last_activity' => null,
            ],
            4 => [
                'id' => 5,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => '[ -._](4u\\.nl|nov[ a]+rip|realco|videomann|vost)[ -._]',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blacklists non-english (release group specific) releases.',
                'last_activity' => null,
            ],
            5 => [
                'id' => 6,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => '[ -.]((bd|dl)mux|doku|\\[foreign\\]|seizoen|staffel)[ -.]',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blacklists non-english (lang specific) releases.',
                'last_activity' => null,
            ],
            6 => [
                'id' => 7,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => '[ -.](imageset|pictureset|xxx)[ -.]',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blacklists porn releases.',
                'last_activity' => null,
            ],
            7 => [
                'id' => 8,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => 'hdnectar|nzbcave',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Bad releases.',
                'last_activity' => null,
            ],
            8 => [
                'id' => 9,
                'groupname' => 'alt\\.binaries\\..*',
                'regex' => 'Passworded',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Removes passworded releases.',
                'last_activity' => null,
            ],
            9 => [
                'id' => 10,
                'groupname' => 'alt\\.binaries\\.(boneless|movies\\.divx)',
                'regex' => '((Frkz|info)@XviD2?|x?VIDZ?@pwrpst|movies@movies?)\\.net|(hsv\\.stoned@hotmail|unequal87@gmail|ilove@movies)\\.com',
                'msgcol' => 2,
                'optype' => 1,
                'status' => 0,
                'description' => 'Virus codec posters.',
                'last_activity' => null,
            ],
            10 => [
                'id' => 11,
                'groupname' => 'alt\\.binaries\\.tun',
                'regex' => '\\[PRiVATE\\]\\s+[a-z0-9]+ ',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Passworded/Encrypted junk.',
                'last_activity' => null,
            ],
            11 => [
                'id' => 12,
                'groupname' => '^alt\\.binaries\\.teevee$',
                'regex' => '^\\[KoreanTV\\] ',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Blocks posts by koreantv.',
                'last_activity' => null,
            ],
            12 => [
                'id' => 13,
                'groupname' => '^alt\\.binaries\\.(kenpsx|frogs)$',
                'regex' => '^\\s*([a-f0-9]{16})\\s\\[\\d+\\/\\d+\\]\\s-\\s\\"\\1\\"\\s+yEnc$',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 0,
                'description' => 'Block 16 character hash floods in kenpsx frogs.',
                'last_activity' => null,
            ],
            13 => [
                'id' => 14,
                'groupname' => '^alt\\.binaries\\.multimedia\\.korean$',
                'regex' => 'TESTMAN',
                'msgcol' => 2,
                'optype' => 1,
                'status' => 1,
                'description' => 'Posts by TESTMAN (jpegs)',
                'last_activity' => null,
            ],
            14 => [
                'id' => 15,
                'groupname' => '^alt\\.binaries\\.multimedia\\.korean$',
                'regex' => '^yEnc ".+torrent"$',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 1,
                'description' => 'torrent uploads ::: yEnc "SBS ÃÃÂ±Ã¢Â°Â¡Â¿Ã¤.E690.120916.HDTV.H264.720p-KOR.avi.torrent"',
                'last_activity' => null,
            ],
            15 => [
                'id' => 16,
                'groupname' => '^korea\\.binaries\\.movies$',
                'regex' => '^.[?(Kornet|SK|xpeed|KT)]?',
                'msgcol' => 1,
                'optype' => 1,
                'status' => 1,
                'description' => 'Incomplete releases',
                'last_activity' => null,
            ],
            16 => [
                'id' => 17,
                'groupname' => '^korea\\.binaries\\.movies$',
                'regex' => '^(top@top.t \\(top\\)|shit@xxxxxxxxaa.com \\(shit\\)|none@nonemail.com \\(none\\))$',
                'msgcol' => 2,
                'optype' => 1,
                'status' => 1,
                'description' => 'incomplete cryptic releases',
                'last_activity' => null,
            ],
            17 => [
                'id' => 18,
                'groupname' => '^korea\\.binaries\\.movies$',
                'regex' => '^filzilla6@web\\.de \\(Baruth\\)$',
                'msgcol' => 2,
                'optype' => 1,
                'status' => 1,
                'description' => 'Virus Poster',
                'last_activity' => null,
            ],
        ]);
    }
}
