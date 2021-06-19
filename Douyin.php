<?php
define('DS', DIRECTORY_SEPARATOR);
defined('APP_PATH') or define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . DS);
defined('ROOT_PATH') or define('ROOT_PATH', (realpath(APP_PATH)) . DS);
defined('RUNTIME_PATH') or define('RUNTIME_PATH', ROOT_PATH . 'runtime' . DS);

class Douyin
{
    private $origin_url = '';
    private $jump_url = '';
    private $video_id = '';

    private $auth_user_id = '';
    private $nickname = '';

    private $video_cover = '';
    private $video_url = '';
    private $video_title = '';

    private $video_json = '';
    private $base_api_url = 'https://www.iesdouyin.com/web';

    protected $save_dir = '';

    /*
    "https://aweme.snssdk.com/aweme/v1/play/?video_id=" + id + "&line=0&ratio=720p&media_type=4&vr_type=0&test_cdn=None&improve_bitrate=0",
    "https://api.amemv.com/aweme/v1/play/?video_id=" + id + "&line=0&ratio=720p&media_type=4&vr_type=0&test_cdn=None&improve_bitrate=0",
    "https://aweme.snssdk.com/aweme/v1/play/?video_id=" + id + "&line=1&ratio=720p&media_type=4&vr_type=0&test_cdn=None&improve_bitrate=0",
    "https://api.amemv.com/aweme/v1/play/?video_id=" + id + "&line=1&ratio=720p&media_type=4&vr_type=0&test_cdn=None&improve_bitrate=0",
    */

    public function execute()
    {
        $this->save_dir = ROOT_PATH . 'video';
        !file_exists($this->save_dir) && mkdir($this->save_dir, 0755, true);
        $origin_url_map = [
            'https://v.douyin.com/WuRMPV',
            'https://v.douyin.com/tStpcu',
            'https://v.douyin.com/V5N1Fp',
            'https://v.douyin.com/CJqK5K',
            'https://v.douyin.com/Cgb8Xh',
            'https://v.douyin.com/93pF4s',
            'https://v.douyin.com/cuBnBN',
            'https://v.douyin.com/4hU9fs',
            'https://v.douyin.com/CgG2eb',
        ];
        foreach ($origin_url_map as $origin_url) {
            $this->downloadVideo($origin_url);
        }
    }

    protected function init()
    {
        $this->origin_url = '';
        $this->jump_url = '';
        $this->video_id = '';
        $this->auth_user_id = '';
        $this->nickname = '';
        $this->video_cover = '';
        $this->video_url = '';
        $this->video_title = '';
        $this->video_json = '';
    }

    public function downloadVideo($origin_url)
    {
        $this->init();
        $res = $this->parse($origin_url);

        $result1 = $this->download($res['title'], $res['cover'], 0);
        echo sprintf('3. 下载抖音视频封面 %s' . PHP_EOL, $res['cover']);

        $result2 = $this->download($res['title'], $res['video'], 1);
        echo sprintf('4. 下载抖音视频 %s' . PHP_EOL, $res['video']);

        echo sprintf('5. 下载结果 封面：%s 视频：%s' . PHP_EOL . PHP_EOL, $result1 ? '√' : '×', $result2 ? '√' : '×');

        $this->savejson($res);
    }

    /**
     * @param $url
     * @return array
     */
    protected function parse($url)
    {
        $this->origin_url = $url;
        $this->parseJumpUrl()->parseVideoId();
        $api_url = sprintf('%s/api/v2/aweme/iteminfo/?item_ids=%s', $this->base_api_url, $this->video_id);
        echo sprintf('2. 解析抖音视频接口地址 %s' . PHP_EOL, $api_url);
        $body = $this->sendRequest($api_url, 1);
        $this->video_json = json_decode($body, true);

        if (!empty($this->video_json) && isset($this->video_json['item_list']) && !empty($this->video_json['item_list'])) {
            $this->parseVideo();
        }

        $res = [
            'user_id' => $this->auth_user_id,
            'nickname' => $this->nickname,
            'cover' => $this->video_cover,
            'video' => $this->video_url,
            'title' => $this->video_title,
        ];
        return $res;
    }

    /**
     * @param $data
     */
    protected function savejson($data)
    {
        $save_file = $this->save_dir . '/' . date('Ymd') . '.md';
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $data = str_replace('\/', '/', $data);
        file_put_contents($save_file, $data . PHP_EOL, FILE_APPEND);
    }

    /**
     * @param $title
     * @param $element_url
     * @param int $type
     */
    protected function download($title, $element_url, $type = 1)
    {
        if (!empty($element_url)) {
            if ($type == 1) {
                $file_mime = 'mp4';
            } else {
                $file_mime = 'jpeg';
            }
            $content = $this->sendRequest($element_url, 1);
            $save_path = $this->save_dir . '/' . $this->auth_user_id;
            !file_exists($save_path) && mkdir($save_path);
            $save_file = $save_path . '/' . $title . '.' . $file_mime;
            $res = file_put_contents($save_file, $content);
            return $res;
        }
        return false;
    }

    /**
     * @return string
     */
    protected function parseVideo()
    {
        $main_node = $this->video_json['item_list'][0];

        $video = $main_node['video'];

        $origin_cover = $video['origin_cover']['url_list'];
        $this->video_cover = $origin_cover[0];

        $origin_url = str_replace('playwm', 'play', $video['play_addr']['url_list']);
        $this->video_url = $this->parseRealVideoUrl($origin_url[0]);

        $share_info = $main_node['share_info'];
        $this->video_title = $share_info['share_title'];

        $this->auth_user_id = $main_node['author_user_id'];
        $this->nickname = $main_node['author']['nickname'];

        return $this;
    }

    /**
     * @param $origin_url
     * @return string|string[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function parseRealVideoUrl($origin_url)
    {
        $video_url_str = $this->sendRequest($origin_url, 0);
        $reg = '#<a href="(.*)">Found</a>#';
        preg_match($reg, $video_url_str, $matchs);
        $video_url = str_replace(['&amp;'], ['&'], urldecode($matchs[1]));
        return $video_url;
    }

    /**
     * @return $this
     */
    protected function parseVideoId()
    {
        $reg = '#video/(.*)/#';
        preg_match($reg, $this->jump_url, $matchs);
        $this->video_id = $matchs[1];
        return $this;
    }

    /**
     * @return $this
     */
    protected function parseJumpUrl()
    {
        echo sprintf('1. 解析抖音分享地址 %s' . PHP_EOL, $this->origin_url);
        $jump_url_str = $this->sendRequest($this->origin_url, 0);
        $reg = '#<a href="(.*)">Found</a>#';
        preg_match($reg, $jump_url_str, $matchs);
        $this->jump_url = str_replace('&amp;', '&', urldecode($matchs[1]));
        return $this;
    }

    /**
     * @param $url
     * @param int $foll
     * @return bool|string
     */
    protected function sendRequest($url, $foll = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //成功返回结果，不输出结果，失败返回false
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //忽略https
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //忽略https
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["user-agent: " . $this->getUa()]); //UA
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $foll); //默认为$foll=0,大概意思就是对照模块网页访问的禁止301 302 跳转。
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * @return array
     */
    private function getRequestHeader($is_redirect)
    {
        $header = [
            'User-Agent' => $this->getUa(),
            'allow_redirects' => $is_redirect ? false : true,
            'verify' => false
//            'connect_timeout' => 5,
        ];
        return $header;
    }

    /**
     * @return string
     */
    private function getUa()
    {
        $arr = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5376e Safari/8536.25',
            "Mozilla/5.0 (Linux; U; Android 8.1.0; zh-cn; BLA-AL00 Build/HUAWEIBLA-AL00) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.132 MQQBrowser/8.9 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 8.1; PAR-AL00 Build/HUAWEIPAR-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.132 MQQBrowser/6.2 TBS/044304 Mobile Safari/537.36 MicroMessenger/6.7.3.1360(0x26070333) NetType/WIFI Language/zh_CN Process/tools",
            "Mozilla/5.0 (Linux; Android 8.1.0; ALP-AL00 Build/HUAWEIALP-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/63.0.3239.83 Mobile Safari/537.36 T7/10.13 baiduboxapp/10.13.0.11 (Baidu; P1 8.1.0)",
            "Mozilla/5.0 (Linux; Android 6.0.1; OPPO A57 Build/MMB29M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/63.0.3239.83 Mobile Safari/537.36 T7/10.13 baiduboxapp/10.13.0.10 (Baidu; P1 6.0.1)",
            "Mozilla/5.0 (Linux; Android 8.1; EML-AL00 Build/HUAWEIEML-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/53.0.2785.143 Crosswalk/24.53.595.0 XWEB/358 MMWEBSDK/23 Mobile Safari/537.36 MicroMessenger/6.7.2.1340(0x2607023A) NetType/4G Language/zh_CN",
            "Mozilla/5.0 (Linux; Android 8.0; DUK-AL20 Build/HUAWEIDUK-AL20; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.132 MQQBrowser/6.2 TBS/044353 Mobile Safari/537.36 MicroMessenger/6.7.3.1360(0x26070333) NetType/WIFI Language/zh_CN Process/tools",
            "Mozilla/5.0 (Linux; U; Android 8.0.0; zh-CN; MHA-AL00 Build/HUAWEIMHA-AL00) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.108 UCBrowser/12.1.4.994 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 8.0; MHA-AL00 Build/HUAWEIMHA-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.132 MQQBrowser/6.2 TBS/044304 Mobile Safari/537.36 MicroMessenger/6.7.3.1360(0x26070333) NetType/NON_NETWORK Language/zh_CN Process/tools",
            "Mozilla/5.0 (Linux; U; Android 8.0.0; zh-CN; MHA-AL00 Build/HUAWEIMHA-AL00) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/40.0.2214.89 UCBrowser/11.6.4.950 UWS/2.11.1.50 Mobile Safari/537.36 AliApp(DingTalk/4.5.8) com.alibaba.android.rimet/10380049 Channel/227200 language/zh-CN",
            "Mozilla/5.0 (Linux; U; Android 8.1.0; zh-CN; EML-AL00 Build/HUAWEIEML-AL00) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.108 UCBrowser/11.9.4.974 UWS/2.13.1.48 Mobile Safari/537.36 AliApp(DingTalk/4.5.11) com.alibaba.android.rimet/10487439 Channel/227200 language/zh-CN",
            "Mozilla/5.0 (Linux; U; Android 4.1.2; zh-cn; HUAWEI MT1-U06 Build/HuaweiMT1-U06) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30 baiduboxapp/042_2.7.3_diordna_8021_027/IEWAUH_61_2.1.4_60U-1TM+IEWAUH/7300001a/91E050E40679F078E51FD06CD5BF0A43%7C544176010472968/1",
            "Mozilla/5.0 (Linux; Android 8.0; MHA-AL00 Build/HUAWEIMHA-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.132 MQQBrowser/6.2 TBS/044304 Mobile Safari/537.36 MicroMessenger/6.7.3.1360(0x26070333) NetType/4G Language/zh_CN Process/tools",
            "Mozilla/5.0 (Linux; U; Android 8.0.0; zh-CN; BAC-AL00 Build/HUAWEIBAC-AL00) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.108 UCBrowser/11.9.4.974 UWS/2.13.1.48 Mobile Safari/537.36 AliApp(DingTalk/4.5.11) com.alibaba.android.rimet/10487439 Channel/227200 language/zh-CN",
            "Mozilla/5.0 (Linux; U; Android 8.1.0; zh-CN; BLA-AL00 Build/HUAWEIBLA-AL00) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.108 UCBrowser/11.9.4.974 UWS/2.13.1.48 Mobile Safari/537.36 AliApp(DingTalk/4.5.11) com.alibaba.android.rimet/10487439 Channel/227200 language/zh-CN",
            "Mozilla/5.0 (Linux; Android 5.1.1; vivo X6S A Build/LMY47V; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/57.0.2987.132 MQQBrowser/6.2 TBS/044207 Mobile Safari/537.36 MicroMessenger/6.7.3.1340(0x26070332) NetType/4G Language/zh_CN Process/tools",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 8_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) CriOS/44.0.2403.67 Mobile/12D508 Safari/600.1.4",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 8_4 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H143 Safari/600.1.4",
            "Mozilla/5.0 (iPad; CPU OS 8_4 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H143 Safari/600.1.4",
            "Mozilla/5.0 (iPad; CPU OS 8_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12F69 Safari/600.1.4",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 8_4_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H321 Safari/600.1.4",
            "Mozilla/5.0 (iPad; CPU OS 7_1_2 like Mac OS X) AppleWebKit/537.51.2 (KHTML, like Gecko) Version/7.0 Mobile/11D257 Safari/9537.53",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 8_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12F70 Safari/600.1.4",
            "Mozilla/5.0 (iPad; CPU OS 8_4_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12H321 Safari/600.1.4",
            "Mozilla/5.0 (iPad; CPU OS 8_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12D508 Safari/600.1.4",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 8_1_3 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Mobile/12B466 [FBAN/FBIOS;FBAV/37.0.0.21.273;FBBV/13822349;FBDV/iPhone6,1;FBMD/iPhone;FBSN/iPhone OS;FBSV/8.1.3;FBSS/2; FBCR/fido;FBID/phone;FBLC/fr_FR;FBOP/5]",
            "Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Mercury/8.7 Mobile/11B554a Safari/9537.53",
        ];
        return $arr[array_rand($arr)];
    }
}

$douyin = new Douyin();
$douyin->execute();