<?php
/**
 * Created by PhpStorm.
 * User: tanteng<tanteng@qq.com>
 * Date: 16/5/8
 * Time: 13:46
 */

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Travel;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Facades\Cache;

class TravelController extends Controller
{
    const CACHE_TIME = 30; //缓存时间，分钟

    public function __construct(Destination $destination, Travel $travel)
    {
        $this->destination = $destination;
        $this->travel = $travel;
        view()->share(['navFlag' => 'travel']);
    }

    //旅行栏目首页，最新游记和目的地
    public function index()
    {
        $data = Cache::remember('travel.index', self::CACHE_TIME, function () {
            $destinationList = $this->destination->getList();
            $travelList = $this->travel->latest('begin_date')->take(8)->get();
            return [
                'destinationList' => $destinationList,
                'travelList' => $travelList,
            ];
        });
        return view('travel.index', $data);
    }

    //全部游记
    public function latest()
    {
        $data = Cache::remember('travel.latest', self::CACHE_TIME, function () {
            $travelList = $this->travel->latest('begin_date')->paginate(20);
            return [
                'travelList' => $travelList,
            ];
        });
        $travelList = $data['travelList'];
        $seoSuffix = '_tanteng.me';
        return view('travel.latest', compact('travelList', 'seoSuffix'));
    }

    //目的地游记列表
    public function travelList($destinationSlug)
    {
        $data = Cache::remember("travel.destination.list.{$destinationSlug}", self::CACHE_TIME, function () use ($destinationSlug) {
            $destinationInfo = $this->destination->where('slug', $destinationSlug)->first(['id', 'destination', 'seo_title', 'description', 'slug']);
            if (!$destinationInfo['id']) {
                abort(404);
            }
            $travelList = $this->travel->travelList($destinationInfo['id']);
            return [
                'travelList' => $travelList,
                'destinationInfo' => $destinationInfo,
            ];
        });

        $travelList = $data['travelList'];
        $destinationInfo = $data['destinationInfo'];

        $seoSuffix = "_tanteng.me";
        return view('travel.destination', compact('travelList', 'destinationInfo', 'seoSuffix'));
    }

    //游记详情
    public function travelDetail($slug)
    {
        //缓存DB数据
        $data = Cache::remember("travel.detail.{$slug}", self::CACHE_TIME, function () use ($slug) {
            $destinationList = $this->destination->getList();
            $detail = $this->travel->where('slug', $slug)->firstOrFail();
            $detail->content = Markdown::convertToHtml($detail->content);
            $destinationInfo = $this->destination->where('id', $detail->destination_id)->first(['id', 'destination', 'slug']);
            $travelList = $this->travel->where('destination_id', $destinationInfo['id'])->where('id', '<>', $detail->id)->latest('begin_date')->take(5)->get(); //10篇同目的地的最新游记
            $travelList = !$travelList->isEmpty() ? $travelList : '';
            return [
                'destinationList' => $destinationList,
                'destinationInfo' => $destinationInfo,
                'detail' => $detail,
                'travelList' => $travelList,
            ];
        });

        $destinationList = $data['destinationList'];
        $destinationInfo = $data['destinationInfo'];
        $detail = $data['detail'];
        $travelList = $data['travelList'];
        $seoSuffix = "_{$destinationInfo->destination}游记_tanteng.me";

        return view('travel.detail', compact('detail', 'destinationList', 'destinationInfo', 'travelList', 'seoSuffix'));
    }
}