<?php

namespace App\Console\Commands;

use Goutte\Client as GoutteClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Pool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Spider extends Command
{

    protected $signature = 'command:spider {concurrency} {keyWords*}'; //concurrency为并发数  keyWords为查询关键词

    protected $description = 'php spider';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        //
        $concurrency = $this->argument('concurrency');  //并发数
        $keyWords = $this->argument('keyWords');    //查询关键词
        $guzzleClent = new GuzzleClient();
        $client = new GoutteClient();
        $client->setClient($guzzleClent);
        $request = function ($total) use ($client,$keyWords){
            foreach ($keyWords as $key){
                $url='https://learnku.com/laravel/search?q='.$key;
                yield function () use($client,$url){
                    return $client->request('GET',$url);
                };
            }
        };
        $pool = new Pool($guzzleClent,$request(count($keyWords)),[
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $index) use ($client){
                $response->filter('h2 > a')->reduce(function($node) use ($client){
                    if(strlen($node->attr('title'))==0) {
                        $title = $node->text();             //文章标题
                        $link = $node->attr('href');        //文章链接
                        $carwler = $client->request('GET',$link);       //进入文章
                        $content=$carwler->filter('#emojify')->first()->text();     //获取内容
                        Storage::disk('local')->put($title,$content);           //储存在本地
                    }
                });
            },
            'rejected' => function ($reason, $index){
                $this->error("Error is ".$reason);
            }
        ]);
        //开始爬取
        $promise = $pool->promise();
        $promise->wait();
    }
}
