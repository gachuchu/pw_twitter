//@charset "utf-8"
//********************************************************************
// twitterのつぶやき表示 v1.0
//********************************************************************

jQuery(document).ready(function($) {
    //--------------------------------------------------------------------
    // ツイートを整形
    //--------------------------------------------------------------------
    var method_tw_tweet_encode = function(tw, opt) {
        if(opt.twlinkfy){
            // tw = tw.replace(/(https?:\/\/[-.!~*\'()\w;\/?:\@&=+\$,%#]+)/gi,'<a href="$1">$1</a>');
            // あえて()は抜いておく
            tw = tw.replace(/(https?:\/\/[-.!~*\'\w;\/?:\@&=+\$,%#]+)/gi,'<a href="$1">$1</a>');
            tw = tw.replace(/@(\w+)/g, '@<a href="http://twitter.com/$1">$1</a>');
            tw = tw.replace(/#(\w+)/g,'<a href="http://twitter.com/#search?q=%23$1">#$1</a>');
        }
        if(opt.twwrap){
            tw = '<' + opt.twwrap + '>' + tw + '</' + opt.twwrap + '>';
        }
        return tw;
    }

    //--------------------------------------------------------------------
    // 時間フォーマット
    //--------------------------------------------------------------------
    var method_tw_get_timeago = function(time, opt) {
        // 変数定義
        var tmdef = {
            //--------------------------------------------------------------------
            // 時間差テーブル
        diffset: {
        justnow:        [0,             1],             // たった今
        second:         [5,             1],             // n秒前
        minute:         [60,            60],            // n分前
        hour:           [50*60,         60*60],         // 約n時間前
        day:            [23.5*3600,     24*3600],       // 約n日前
        week:           [6.5*24*3600,   7*24*3600],     // 約n週間前
        month:          [25*24*3600,    30*24*3600],    // 約nヶ月前
        year:           [360*24*3600,   365*24*3600]    // 約n年前
        },

            //--------------------------------------------------------------------
            // 時間差の表示
        getago: {
        justnow:        function(n){ return 'たった今'; },
        second:         function(n){ return n + '秒前'; },
        minute:         function(n){ return n + '分前'; },
        hour:           function(n){ return '約' + n + '時間前'; },
        day:            function(n){ return '約' + n + '日前'; },
        week:           function(n){ return '約' + n + '週間前'; },
        month:          function(n){ return '約' + n + 'ヶ月前'; },
        year:           function(n){ return '約' + n + '年前'; }
        },

            //--------------------------------------------------------------------
            // ロケール対応
        locale: {
        a: ['日', '月', '火', '水', '木', '金', '土'],
        A: ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'],
        b: ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
        B: ['睦月', '如月', '弥生', '卯月', '皐月', '水無月', '文月', '葉月', '長月', '神無月', '霜月', '師走'],
        p: ['AM', 'PM'],
        P: ['午前', '午後']
        },

            //--------------------------------------------------------------------
            // 変換
        converter : {
        a: function(t){ return tmdef.locale.a[t.getDay()];                                                          }, // 曜日の省略名
        A: function(t){ return tmdef.locale.A[t.getDay()];                                                          }, // 曜日の正式名
        b: function(t){ return tmdef.locale.b[t.getMonth()];                                                        }, // 月の省略名
        B: function(t){ return tmdef.locale.B[t.getMonth()];                                                        }, // 月の正式名
        d: function(t){ return '' + t.getDate();                                                                    }, // 日付(1～31)
        D: function(t){ return tmdef.pad0(t.getDate(), 2);                                                          }, // 日付(01～31)
        h: function(t){ return '' + t.getHours();                                                                   }, // 時間(0～23)
        H: function(t){ return tmdef.pad0(t.getHours(), 2);                                                         }, // 時間(00～23)
        i: function(t){ return (t.getHours()%12) ? '' + t.getHours()%12 : 12;                                       }, // 時間(1～12)
        I: function(t){ return (t.getHours()%12) ? tmdef.pad0(t.getHours()%12, 2) : 12;                             }, // 時間(01～12)
        j: function(t){ return '' + (t.getMonth()+1);                                                               }, // 月(1～12)
        J: function(t){ return tmdef.pad0(t.getMonth()+1, 2);                                                       }, // 月(01～12)
        m: function(t){ return '' + t.getMinutes();                                                                 }, // 分(0～59)
        M: function(t){ return tmdef.pad0(t.getMinutes(), 2);                                                       }, // 分(00～59)
        p: function(t){ return tmdef.locale.p[parseInt(t.getHours()/12)];                                           }, // AM/PM
        P: function(t){ return tmdef.locale.P[parseInt(t.getHours()/12)];                                           }, // 午前/午後
        s: function(t){ return '' + t.getSeconds();                                                                 }, // 秒(0～59)
        S: function(t){ return tmdef.pad0(t.getSeconds(), 2);                                                       }, // 秒(00～59)
        y: function(t){ return tmdef.pad0((t.getFullYear() < 2000) ? t.getFullYear() + 1900 : t.getFullYear(), 2);  }, // 西暦の下2桁(00～99)
        Y: function(t){ return tmdef.pad0((t.getFullYear() < 2000) ? t.getFullYear() + 1900 : t.getFullYear(), 4);  }, // 4桁の西暦
        },

            //--------------------------------------------------------------------
            // 0埋め
        pad0: function(x, n){
            var str = "0000" + x;
            return str.slice(-n);
        }
        };

        time = time || new Date();
        time = new Date(time);

        var diff      = ((new Date()) - time) / 1000;
        var gap_key   = 'justnow';
        var gap_index = 0;
        var gqa_limit = 999;
        var i         = 0;
        var ago       = '';

        // 時間差をしらべる
        for(var key in tmdef.diffset){
            if(diff >= tmdef.diffset[key][0]){
                gap_key  = key;
                gap_index = i;
            }
            ++i;
            if(key == opt.tmago){
                gap_limit = i;
            }
        }

        // 時間差を指定文字列で返す
        if(gap_index < gap_limit){
            // timeago
            ago = parseInt(diff / tmdef.diffset[gap_key][1]);
            if(ago <= 0){
                ago = 1;
            }
            ago = tmdef.getago[gap_key].call(ago, ago);
        }else{
            // format
            ago = opt.tmformat.replace(/%([aAbBdDhHiIjJmMpPsSyY%])/g, function(m0, m1){
                var f = tmdef.converter[m1];
                if(typeof(f) == 'function'){
                    return f.call(time, time);
                }else{
                    return m1;
                }
            });
        }

        return ago;
    }

    //--------------------------------------------------------------------
    // 時間の表示
    //--------------------------------------------------------------------
    var method_tw_time_encode = function(time, opt, json) {
        time.replace(/\+0000/, 'GMT');
        time = method_tw_get_timeago(time, opt);
        if(opt.tmlinkfy){
            time = '<a href="http://twitter.com/' + json.user.screen_name + '/status/' + json.id_str + '" rel="nofollow" title="このつぶやきを見る">' + time + '</a>';
        }
        if(opt.tmwrap){
            time = "<" + opt.tmwrap + ">" + time + "</" + opt.tmwrap + ">";
        }
        return time;
    }

    //--------------------------------------------------------------------
    // つぶやき領域を処理
    //--------------------------------------------------------------------
    $('.pwtw-tweet').each(function() {
        var copythis = this;
        var opt = $(this).data('pwtw');

        // オプション解析
        opt = $.extend(
            {
            count:              1,
            max:                1,
            negativematch:      null,
            wrap:               null,
            twdefault:          'つぶやきを取得できませんでした',
            twlinkfy:           true,
            twwrap:             null,
            tmago:              'hour',
            tmformat:           "%j月%d日 %A %p%i時%m分",
            tmlinkfy:           true,
            tmwrap:             'span'
            },
            opt);

        if(opt.count > opt.max){
            opt.max = opt.count;
        }
        opt.tweet = opt.twdefault;

        // つぶやき取得と表示処理
        var api = opt.api + "?callback=?";
        $.getJSON(api,
                  { count: opt.max },
                  function(json){
                      if(json.length > 0){
                          opt.tweet   = "";
                          var loopnum = opt.count;
                          var dispnum = 0;
                          var regx    = false;
                          if(opt.negativematch){
                              var regx = new RegExp(opt.negativematch);
                          }
                          for(var i = 0; i < json.length; i++){
                              var tw = method_tw_tweet_encode(json[i].text, opt);
                              if(regx != false && tw.match(regx)){
                                  continue;
                              }
                              var tm = '';
                              if(opt.tmago){
                                  tm = method_tw_time_encode(json[i].created_at, opt, json[i]);
                              }
                              tw = tw + tm;
                              if(opt.wrap){
                                  tw = "<" + opt.wrap + ">" + tw + "</" + opt.wrap + ">";
                              }
                              opt.tweet = opt.tweet + tw;
                              loopnum--;
                              if(loopnum <= 0){
                                  break;
                              }
                          }
                      }
                      $(copythis).html(opt.tweet);
                  });
    });
});
