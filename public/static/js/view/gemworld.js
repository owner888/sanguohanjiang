(function() {
    gemWorldClass = function() {
        this.e;

        this.ltDom; //各种dom
        this.lbDom;
        this.rtDom;
        this.rbDom;

        this.siid; //选中的道具的id
        this.sgid; //选中的宝石id
        this.leftGemNum; //剩余的宝石数量

        this.gemScroller;

        this.eqPosList; //装备中的将魂坐标集合
        this.uneqPosList; //未装备的将魂位置坐标集合
        this.touchGid;
        this.touchPos;
        this.canMove; //可以移动的集合
        this.nearlyX; //最近元素的x坐标
        this.nearlyY; //最近元素的y坐标
        this.nearlyPosId; //最近的元素的posid
        this.zIndex; //层级
        this.distance; //检测范围

        this.page; //当前页页码
        this.pages; //当前有效页数，也就是有道具的页数

        this.entireDom; //宝石合成整个页面
        this.mergegemData = null; //进入界面接收的数据
        this.tmplDom = null; //模板dom
        this.infoDom = null; //模板对应信息dom
        this.gemInfoDom = null; //宝石信息dom
        this.packDom = null; //背包dom
        this.nowChoiceTemp = 1; //现在选择的模板
        this.nowpages = 1; //当前总页面
        this.nowpage = 1; //当前页面
        this.GemleftNum = 0; //当前所剩碎片
        this.packeqPosList = null; //背包的坐标
        this.nowChoiceId = null; //当前选择宝石的gid
        this.meetGid = null; //当前移动的宝石的gid
        this.meetPos = null; //当前移动宝石的位置 
        this.nowOneSeat = null; //左边位置放置宝石的gid，无宝石为null
        this.nowTwoSeat = null; //右边位置放置宝石的gid，无宝石为null
        this.modelDom = null; //放置宝石的位置
        this.templetTime = null; //模板生成scroll的延时
        this.mergegemScroll = null; //模板宝石的移动scroll
    };
    gemWorldClass.prototype = {
        init: function() {
            var that = this;

            var Func = function(res) {
                res.status = parseInt(res.status);
                if (res.status == 1) {
                    that.initFirst(res);
                } else {
                    var error_msg = '';
                    switch (res.status) {
                    case - 1 : error_msg = lang('champions_get_info_fail');
                        break;
                    case 2 : error_msg = lang('gemworld_lv_140');
                        break;
                    default:
                        error_msg = lang('undefined_error');
                        break;
                    }
                    xGame.toast.show(error_msg);
                    xGame.loadWorld('city');
                    return false;
                }
                that = null;
            }
            loadContents({
                'c': 'gem',
                'm': 'index'
            },
            Func, false,
            function() {
                xGame.loadWorld('city');
            }); //加载数据

            Func = null;
        },

        initFirst: function(res) {
            var that = this;

            if (that.e != null) {
                return false;
            }

            var dom_world = $('#world');
            dom_world.append($.template('gemworld_tmpl'));
            that.e = dom_world.find('#gemworld_page');
            this.entireDom = dom_world.find('#mergegem_page');
            that.e.find('#gemworld_hide').unbind('touchend').bind('touchend',
            function(e) {
                if (xGame.checkLock()) {
                    return false;
                }
                xGame.loadWorld('city');
                e.preventDefault();
                e.stopPropagation();
            });
            dom_world = null;

            that.ltDom = that.e.find('#gemworld_lt');
            that.lbDom = that.e.find('#gemworld_lb');
            that.rtDom = that.e.find('#gemworld_rt');
            that.rbDom = that.e.find('#gemworld_rb');
            this.tmplDom = that.entireDom.find('.mergegem_lt');
            this.infoDom = that.entireDom.find('.mergegem_animal_info');
            this.packDom = that.entireDom.find('#mergegem_rb');
            this.gemInfoDom = that.entireDom.find('#mergegem_rt');
            this.modelDom = that.entireDom.find('.mergegem_animal_compose');

            that.zIndex = 10;
            that.distance = that.getDistance(75, 75);
            that.page = 1;
            that.pages = parseInt(res.pack.allpage);

            that.eqPosList = {
                'pos1': {
                    'left': -370,
                    'top': 40,
                    'style': 'left:-370px;top:40px;',
                    'id': 1
                },
                'pos2': {
                    'left': -250,
                    'top': 10,
                    'style': 'left:-250px;top:10px;',
                    'id': 2
                },
                'pos3': {
                    'left': -130,
                    'top': 40,
                    'style': 'left:-130px;top:40px;',
                    'id': 3
                },
                'pos4': {
                    'left': -90,
                    'top': 150,
                    'style': 'left:-90px;top:150px;',
                    'id': 4
                },
                'pos5': {
                    'left': -130,
                    'top': 265,
                    'style': 'left:-130px;top:265px;',
                    'id': 5
                },
                'pos6': {
                    'left': -250,
                    'top': 305,
                    'style': 'left:-250px;top:305px;',
                    'id': 6
                },
                'pos7': {
                    'left': -370,
                    'top': 265,
                    'style': 'left:-370px;top:265px;',
                    'id': 7
                },
                'pos8': {
                    'left': -407,
                    'top': 150,
                    'style': 'left:-407px;top:150px;',
                    'id': 8
                },
            };
            var x, y;
            that.uneqPosList = {};
            for (var i = 1; i < 13; i++) {
                x = 35 + (i - 1) % 4 * 113;
                y = 42 + Math.floor((i - 1) / 4) * 107;
                that.uneqPosList['pos' + i] = {
                    'left': x,
                    'top': y,
                    'style': 'left:' + x + 'px;top:' + y + 'px;',
                    'id': i
                };
            }

            //背包的left和top
            this.packeqPosList = {};
            for (var i = 1; i < 10; i++) {
                x = 15 + (i - 1) % 3 * 107;
                y = 12 + Math.floor((i - 1) / 3) * 101;
                that.packeqPosList['pos' + i] = {
                    'left': x,
                    'top': y,
                    'style': 'left:' + x + 'px;top:' + y + 'px;',
                    'id': i
                };
            }

            //放置宝石的left和top
            that.unpackeqPosList = {
                'pos1': {
                    'left': -405,
                    'top': 145,
                    'style': 'left:-406px;top:145px;',
                    'id': 1
                },
                'pos2': {
                    'left': -193,
                    'top': 145,
                    'style': 'left:-193px;top:145px;',
                    'id': 2
                },
            };

            that.leftGemNum = parseInt(res.pack.chip);
            that.fillRb(res.pack);
            that.fillRt(res.gem);

            var html1 = '';

            for (var i = 0; i < res.eid_list.length; i++) {
                if (i == 0) {
                    that.siid = res.eid_list[i].id;
                    that.fillLb(res.eid_list[i]);
                    html1 += '<li id="itemid' + res.eid_list[i].id + '" iid="' + res.eid_list[i].id + '" class="item selected"><img src="' + xGame.itemurl(res.eid_list[i].equipid) + '" /><span>' + res.eid_list[i].generalname + '</span></li>';
                } else {
                    html1 += '<li id="itemid' + res.eid_list[i].id + '" iid="' + res.eid_list[i].id + '" class="item"><img src="' + xGame.itemurl(res.eid_list[i].equipid) + '" /><span>' + res.eid_list[i].generalname + '</span></li>';
                }
            }
            
            if (res.eid_list.length < 3) {
                for (var i = 1;i <= 3 - res.eid_list.length;i ++) {
                    html1 += '<li class="block_li"></li>';
                }
            }
            
            that.ltDom.find('#gemworld_list').width((res.eid_list.length < 3 ? 3 : res.eid_list.length) * 113 + 'px').html(html1);
            html1 = null;

            that.gemScroller = new iScroll('gemworld_scroller', {
                snap: 'li',
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            that.gemScroller.refresh();

            that.bindAll();


            $('#worldloading').removeClass('black').hide();
        },

        //填充页面数据
        fillFData: function(){
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        that.mergegemData = res;
                        that.buildMergegemInfo();
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                    }
                    xGame.unlock();
            };

            loadContents({
                'c': 'gem',
                'm': 'mergegem_index'
            },
            initRender);
            initRender = null; 
        },

        //填充页面信息
        buildMergegemInfo: function(){
            var that = this;
            var temp = this.mergegemData.temp;
            var html = "";
            var len = 0;
            var num = 0;

            for (var i = 0 , len = temp.length; i < len; i++) {
                html +='<li class="mergegem_item" id="mergegem_animals'+ (i+1) +'" bid="'+ (i+1) +'"><span class="mergegem_animals_bg" style="background-image: url('+ xGame.gem(i+1, 6) +');"></span><span class="mergegem_animals_bg2">'+ temp[i].name +'</span></li>'
            };
            this.tmplDom.find('#mergegem_list').html(html);

            this.GemleftNum = parseInt(this.mergegemData.chip);

            this.showTempInfo();
            this.fillMergegemPack(this.mergegemData);
            this.buildAnimalsInfo(this.mergegemData.gem);

            this.packDom.find('#gemS' + this.nowChoiceId).addClass('choice');

            this.mergegemScroll = new iScroll('mergegem_scroller',{
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });
            this.templetTime = setTimeout(function(){
                that.mergegemScroll.refresh();
            },500);
        },

        //展示模板信息
        showTempInfo: function(num){
            var now = 0;
            if (num) {
                this.tmplDom.find('#mergegem_animals'+ this.nowChoiceTemp +'').removeClass('selected');
                this.nowChoiceTemp = num;
            }
            now = this.nowChoiceTemp;
            var data = this.mergegemData.temp[now - 1];
            var type = parseInt(data.type);
            var html = lang('propertyshow');

            this.tmplDom.find('#mergegem_animals'+now+'').addClass('selected');
            this.infoDom.find('.mergegem_animal_tittle').text(data.name);
            this.infoDom.find('.mergegem_animal_parts').find('.kind').css('color',helper.getItemColor(data.quality));
            
            this.infoDom.find('.mergegem_animal_parts').find('.kind').text(lang('gemworld_two_parts') + lang('fulllevel' +type));
            html += helper.getTalent(type);
            html += '+' + data.level21;
            this.infoDom.find('.mergegem_animal_property').text(html);
        },

         //填充合成页面的背包
        fillMergegemPack: function(data, isRebuild) {
            this.fillCurrentMergegem(parseInt(data.chip));
            this.nowpages = parseInt(data.allpage); 
            var num = parseInt(data.num);
            if (data.page) {
                this.nowpage = parseInt(data.page);
            }
        
            if (this.nowpage == 1) {
                //第一页的时候针对下一页按钮做特殊处理
                if (this.nowpage == this.nowpages) {
                    this.packDom.find('#mergegem_next').css('display', 'none');
                } else {
                    this.packDom.find('#mergegem_next').css('display', 'block');
                }
                this.packDom.attr('class', 'isfirst');
            } else if (this.nowpage == this.nowpages) {
                this.packDom.find('#mergegem_next').css('display', 'none');
                this.packDom.attr('class', 'islast');
            } else {
                this.packDom.removeClass();
                this.packDom.find('#mergegem_next').css('display', 'block');
            }
            
            this.packDom.find('#gem_page_num').html(this.nowpage);
            
            var html = '';
            var html1 = '';
            var j = 0;
            for (var i = 0; i < 9; i++) {
                if (i + 1 + (this.nowpage - 1) * 9 <= num ) {
                    if (data.list[i] != undefined && this.nowOneSeat != data.list[i].id && this.nowTwoSeat != data.list[i].id ) {
                        html += '<li class="gem_t' + data.list[i].quality + ' mergegem_pack_item" gid="' + data.list[i].id + '" id="gemS' + data.list[i].id + '"></li>';
                        html1 += '<span class="isunequip" style="background-image:url(' + xGame.gem(data.list[i].type ,data.list[i].quality) + ');' + this.packeqPosList['pos' + (i + 1 - j)].style + '" id="gem_span' + data.list[i].id + '" posid="' + (i + 1 - j) + '" gid="' + data.list[i].id + '" type="' + data.list[i].quality + '" ><em class="gem_name">' + helper.getTalent(data.list[i].type) + '</em></span>';
                    }else if (data.list[i] != undefined ) {
                        if (this.nowOneSeat == data.list[i].id || this.nowTwoSeat == data.list[i].id) {
                             j++;
                        }  
                    }else {
                        html += '<li class="block"></li>';
                    }
                } else{
                     html += '<li class="block"></li>';
                }
            }
            if (data.gem) {
                 this.nowChoiceId = data.gem.id;
            }
            for(var k = 0; k < j; ++k){
                 html += '<li class="block"></li>';
            }
            if (isRebuild == undefined) {
                this.packDom.find('#mergegem_pack_list').html(html + html1);
            }else {
                this.packDom.find('#mergegem_pack_list').find('li').remove();
                this.packDom.find('#mergegem_pack_list').find('.isunequip').remove();
                this.packDom.find('#mergegem_pack_list').append(html + html1);
            }
           
            html = html1 = null;
        },

        //更改当前宝石碎片的个数
        fillCurrentMergegem: function(num) {
            if (num == undefined) {
                return false;
            }
            var that = this;

            if (that.GemleftNum != num) {
                xGame.doAnimate2(that.packDom.find('#mergegem_own'), 'changcolor', 100, 7,
                function() {
                    if (that.packDom) {
                        that.packDom.find('#mergegem_own').html(num);
                    }
                });
                that.GemleftNum = num;
            } else {
                that.packDom.find('#mergegem_own').html(num);
            }
        },

        //获取翻页后端返回信息
        turnPages: function(num) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        if (!that.modelDom.find('#mergegem_compose_part1').hasClass('selected') && !that.modelDom.find('#mergegem_compose_part2').hasClass('selected')){
                            that.entireDom.find('#mergegem_rt').attr('class','nodata');
                            that.nowChoiceId = null;
                        }
                        that.fillMergegemPack(res ,true);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                    }
                    xGame.unlock();
            };

            loadContents({
                'c': 'gem',
                'm': 'get_20gem_by_page',
                'page': num
            },
            initRender);
            initRender = null; 
        },

        //获取某个宝石的信息
        getMergegemInfoById: function(obj) {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var initRender = function(res) {

                switch (parseInt(res.status)) {
                    case 1:         
                        that.packDom.find('#gemS' + that.nowChoiceId).removeClass('choice');
                        that.nowChoiceId = obj.attr('gid');
                        that.packDom.find('#gemS' + that.nowChoiceId).addClass('choice');
                        that.buildAnimalsInfo(res.gem);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                    }
                    xGame.unlock();
            };

            loadContents({
                'c': 'gem',
                'm': 'get_gem_info',
                'id': obj.attr('gid')
            },
            initRender);
            initRender = null; 
        },

        //构建某个宝石信息的展示
        buildAnimalsInfo: function(data){
            if (data.id == undefined) {
                this.gemInfoDom.attr('class', 'nodata');
                return false;
            }
            this.gemInfoDom.attr('class', 'hasdata');

            this.gemInfoDom.find('#mergegem_change').text(lang('gemworld_turn_part'));
            this.gemInfoDom.find('#mergegem_levelup').text(lang('level_up'));

            if (data.ismax == 0) {
                this.gemInfoDom.find('#mergegem_levelup').css('display', 'block');
                this.gemInfoDom.find('#mergegem_abnow').html(lang('soul_properties') + ':' + helper.getTalent(parseInt(data.type)) + '+<em>' + data.power + '</em></br><em id="gem_abnext">(' + lang('next_lv') + '+' + data.next_power + ')</em>');
                this.gemInfoDom.find('#mergegem_lv').html(data.need_chip);
                this.gemInfoDom.find('.mergegem_lvneed').css('display', 'block');
            } else {
                this.gemInfoDom.find('#mergegem_levelup').css('display', 'none');
                this.gemInfoDom.find('#mergegem_abnow').html(lang('soul_properties') + ':' + helper.getTalent(parseInt(data.type)) + '+<em>' + data.power + '</em>');
                this.gemInfoDom.find('.mergegem_lvneed').css('display', 'none');
            }

            this.gemInfoDom.find('#mergegem_name').html(data.name + '<em id="gem_level" style="color:' + helper.getItemColor(data.quality) + ';" >Lv.' + data.level + '</em>').css('color', helper.getItemColor(data.quality));
            this.gemInfoDom.find('#selected_mergegem').attr({
                'class': 'gem_t' + data.quality + ' gem_ts',
                'btype': data.type
            }).find('.selected_mergegem_pic').css('background-image', 'url(' + xGame.gem(data.type,data.quality) + ')');

            if (data.need_chip > this.GemleftNum) {
                this.gemInfoDom.find('#gem_lvneed').attr('class', 'buzu');
            } else {
                this.gemInfoDom.find('#gem_lvneed').removeClass('buzu');
            }
        },

        //获取升级信息
        getLevelupInfo: function(){
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var initRender = function(res) {
                switch (parseInt(res.status)) {
                    case 1:
                        that.mergegemLevelup(res);
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                    case - 6 : xGame.toast.show(lang('level_up_fail'));
                        break;
                    case - 5 : xGame.toast.show(lang('gemworld_part_not_enough'));
                        break;
                    case - 4 : xGame.toast.show(lang('gemworld_get_tmpl_fail'));
                        break;
                    case - 3 : xGame.toast.show(lang('gemworld_get_lvup_info_fail'));
                        break;
                    case - 2 : xGame.toast.show(lang('gemworld_gem_lv_full'));
                        break;
                    case - 1 : xGame.toast.show(lang('gemworld_get_info_fail'));
                        break;
                    }
                    xGame.unlock();
            };

            loadContents({
                'c': 'gem',
                'm': 'level_up',
                'id': that.nowChoiceId
            },
            initRender);
            initRender = null; 
        },

        //升级宝石
        mergegemLevelup: function(res){
            res.need_chip = parseInt(res.need_chip);
            this.fillCurrentMergegem(res.all_chip);
            this.gemInfoDom.find('#mergegem_abnow').find('em').html(res.power);
            this.gemInfoDom.find('#gem_level').html('Lv.' + res.level).css('color', helper.getItemColor(res.quality));
            this.gemInfoDom.find('#mergegem_name').css('color', helper.getItemColor(res.quality));
            this.gemInfoDom.find('#gem_name').css('color', helper.getItemColor(res.quality));
            if (res.ismax == 0) {
                this.gemInfoDom.find('#gem_abnext').html('(' + lang('next_lv') + ' +' + res.next_power + ')');
                this.gemInfoDom.find('#mergegem_lv').html(res.need_chip);
                if (res.need_chip > res.all_chip) {
                    this.gemInfoDom.find('#mergegem_lv').attr('class', 'buzu');
                }
                this.gemInfoDom.find('.mergegem_lvneed').css('display', 'block');
                this.gemInfoDom.find('#gem_abnext').css('display', 'block');
            } else {
                this.gemInfoDom.find('#mergegem_levelup').css('display', 'none');
                this.gemInfoDom.find('.mergegem_lvneed').css('display', 'none');
                this.gemInfoDom.find('#gem_abnext').css('display', 'none');
            }
            var btype = this.gemInfoDom.find('#selected_mergegem').attr('btype');
            this.gemInfoDom.find('#selected_mergegem').attr('class', 'gem_t' + res.quality + ' gem_ts').find('span').css('background-image', 'url(' + xGame.gem(btype,res.quality)+')');
            this.packDom.find('.choice').attr('class', 'gem_t' + res.quality + ' choice mergegem_pack_item');
            this.packDom.find('#gem_span' + this.nowChoiceId).attr('type', res.quality).css('background-image', 'url(' + xGame.gem(btype,res.quality)+')');
            btype = null;
        
            var obj = this.packDom.find('#gem_span' + this.nowChoiceId);
            var posid = obj.attr('posid');
           
            if (obj.hasClass('isunequip')) {
                this.newlvupAnim(this.packeqPosList['pos' + posid].left, this.packeqPosList['pos' + posid].top);
            } else {
                this.newlvupAnim(this.unpackeqPosList['pos' + posid].left, this.unpackeqPosList['pos' + posid].top);
            }
        },

        //获取转换信息
        getconvertInfo: function(confirm){
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {

                switch (parseInt(res.status)) {
                    case 1:
                        if (confirm == undefined) {
                            xGame.confirm.show(lang('gemworld_turn_part'), lang('gemworld_turn_part_ask', [res.chip]), 2,
                            function() {
                                that.getconvertInfo(1);
                                xGame.confirm.hide();
                            },
                            function() {
                                xGame.confirm.hide();
                            });
                        } else {
                            that.convertInfo(res);
                            
                       }
                        break;
                    case - 2 :
                        xGame.toast.show(lang('gemworld_get_info_fail'));
                        break;
                    case - 1 :
                        xGame.toast.show(lang('gemworld_turn_fail'));
                        break;
                    case 2:
                        xGame.toast.show(lang('gemworld_lv_140'));
                        break;
                    default :
                        xGame.toast.show(res.msg);
                        break;
                    }
                    xGame.unlock();
            };

            if (confirm == undefined) {
                loadContents({
                    'c': 'gem',
                    'm': 'gem_turn',
                    'id': that.nowChoiceId
                },
                Func);
            } else {
                loadContents({
                    'c': 'gem',
                    'm': 'gem_turn',
                    'id': that.nowChoiceId,
                    'confirm': confirm
                },
                Func);
            }
            Func = null;
        },

        //转换
        convertInfo: function(res){
            var that = this;
            that.fillCurrentMergegem(res.all_chip);
            /*如果要转的将魂在背包里*/
            var obj = that.packDom.find('#gem_span' + that.nowChoiceId);
            
            if (obj.hasClass('isunequip')) { //如果要转的将魂在背包里
                var pos = parseInt(obj.attr('posid'));
                var gid = that.packDom.find('.isunequip[posid="' + (pos + 1) + '"]').attr('gid');

                if (gid == undefined) { //先看后面的，再看前一个
                    gid = that.packDom.find('.isunequip[posid="' + (pos - 1) + '"]').attr('gid');
                    if (gid == undefined) {
                        that.gemInfoDom.attr('class', 'nodata');
                    } else {
                        /*特殊处理，解锁了才能请求*/
                        xGame.unlock();
                        that.getMergegemInfoById(that.packDom.find('#gem_span' + gid));
                    }
                } else {
                    xGame.unlock();
                    that.getMergegemInfoById(that.packDom.find('#gem_span' + gid));
                }
                that.refreshPackBynowChoiceId(that.nowChoiceId);
                pos = null;
                gid = null;
            } else { //如果要转的将魂在身上里
                that.gemInfoDom.attr('class', 'nodata');
                if (that.modelDom.find('#mergegem_compose_part1').hasClass('selected')) {
                    that.modelDom.find('#mergegem_compose_part1').removeClass('selected');
                    that.nowOneSeat = null;
                }else if (that.modelDom.find('#mergegem_compose_part2').hasClass('selected')) {
                    that.modelDom.find('#mergegem_compose_part2').removeClass('selected');
                    that.nowTwoSeat = null;
                } 
            }
            obj.remove();
        },

        //转化后页面处理
        refreshPackBynowChoiceId: function(gid) {
            var opos = parseInt(this.packDom.find('#gem_span' + gid).attr('posid')); //原来的位置ID
            this.packDom.find('#gemS' + gid).remove();

            /*li底图的移动*/
            if (this.packDom.find('.block').size() == 0) {
                if (this.packDom.find('.closed').size() != 0) {
                    this.packDom.find('.closed').first().before('<li class="block"></li>');
                } else {
                    this.packDom.find('ul').append('<li class="block"></li>');
                }
            } else {
                this.packDom.find('.block').last().after('<li class="block"></li>');
            }
            /*span的更新*/
            var pos;
            for (var i = 1; i <= this.packDom.find('.isunequip').size(); i++) {
                pos = parseInt(this.packDom.find('.isunequip').eq(i - 1).attr('posid'));
                if (pos > opos) {
                    this.packDom.find('.isunequip').eq(i - 1).css({
                        'left': this.packeqPosList['pos' + (pos - 1)].left + 'px',
                        'top': this.packeqPosList['pos' + (pos - 1)].top + 'px'
                    }).attr('posid', (pos - 1));
                }
            }
            opos = pos = null;
        },

        //合成页面升级动画
        newlvupAnim: function(x,y) {
            var that = this;
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/gemworld_lvup.png'),
                "width": 150,
                "height": 250,
                "x": x - 36,
                "y": y - 90,
                "loop": false,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 40
                },
                {
                    rect: [150, 0],
                    interval: 40
                },
                {
                    rect: [300, 0],
                    interval: 40
                },
                {
                    rect: [450, 0],
                    interval: 40
                },
                {
                    rect: [6000, 0],
                    interval: 40
                }]
            });
            //开始播放
            mc.appendTo(this.packDom);
            mc.play();
            mc = null;
        },

        //绑定宝石开始移动，正在移动，结束移动各个状态
        bindmergegem: function(){
            var that = this;

            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            var target = null;

            //开始移动
            this.packDom.undelegate('span','touchstart').delegate('span','touchstart',function(event){
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                
                if ($(this).attr('gid') != that.nowChoiceId) {
                    that.getMergegemInfoById($(this));
                }

                that.meetGid = $(this).attr('gid');
                that.meetPos = $(this).attr('posid');
                /*方阵外面时就复制一个元素占着那个位置*/
                if ($(this).hasClass('isequip')) {
                    ex = parseInt(that.unpackeqPosList['pos' + that.meetPos].left);
                    ey = parseInt(that.unpackeqPosList['pos' + that.meetPos].top);
                } else {
                    ex = parseInt(that.packeqPosList['pos' + that.meetPos].left);
                    ey = parseInt(that.packeqPosList['pos' + that.meetPos].top);
                    that.modelDom.find('#mergegem_compose_part1').removeClass('selected');
                    that.modelDom.find('#mergegem_compose_part2').removeClass('selected');
                }

                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);

                $(this).addClass('ismoving');
                $(this).css('z-index', that.zIndex++);
                
                target = $(this);

                if (that.meetGid == that.nowOneSeat) {
                    that.modelDom.find('#mergegem_compose_part1').removeClass('selected').addClass('selected');
                    that.modelDom.find('#mergegem_compose_part2').removeClass('selected');
                }else if (that.meetGid == that.nowTwoSeat) {
                    that.modelDom.find('#mergegem_compose_part1').removeClass('selected');
                    that.modelDom.find('#mergegem_compose_part2').removeClass('selected').addClass('selected');
                }
            }); 

            //在移动中
            that.entireDom.unbind('touchmove').bind('touchmove',function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);

                    var newX = nX - ox;
                    var newY = nY - oy;
                    var realX = ex + newX;
                    var realY = ey + newY;
                    target.css({
                        left: realX + 'px',
                        top: realY + 'px'
                    });
                }
            });

             /*移动结束*/
            that.packDom.find('ul').undelegate('span.ismoving', 'touchend').delegate('span.ismoving', 'touchend',function(event) {     
                target = null;
                $(this).removeClass('ismoving');
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = $(this);
                var myx = parseInt(obj.css('left').replace('px', ''));
                var myy = parseInt(obj.css('top').replace('px', ''));

                /*如果坐标最后都没变化，就直接不管它*/
                if (myx == ex && myy == ey) {
                    return false;
                }
                /*移动的是已装备的将魂*/
                if (obj.hasClass('isequip')) {
                    /*有位置可以移动，判断最近的位置*/
                   if (!that.checkPack(myx, myy)) { //合法，到最近的位置上去
                         obj.animate({
                            'left': that.unpackeqPosList['pos' + that.meetPos].left + 'px',
                            'top': that.unpackeqPosList['pos' + that.meetPos].top + 'px'
                        },
                        100, 'ease', null);
                    }else {
                        // obj.attr('class','isunequip');
                        var size = that.packDom.find('.block').size();
                        if (size == 0) { //如果当前页满课，直接删除
                            obj.remove();
                        }else { //没有满的话就加到队末
                                    /*底*/
                            that.packDom.find('.block').eq(0).before('<li class="gem_t' + obj.attr('type') + ' choice mergegem_pack_item" gid ="' + obj.attr('gid') + '" id="gemS' + obj.attr('gid') + '"></li>');
                            that.packDom.find('.block').eq(0).remove();
                            var posid = 9 - that.packDom.find('.block').size();
                            obj.attr({
                                'class': 'isunequip',
                                'posid': posid
                            });
                            obj.animate({
                                'left': that.packeqPosList['pos' + posid].left + 'px',
                                'top': that.packeqPosList['pos' + posid].top + 'px'
                            },
                            100, 'ease', null);
                        }
                        that.modelDom.find('#mergegem_compose_part1').removeClass('selected');
                        that.modelDom.find('#mergegem_compose_part2').removeClass('selected');
                        if (that.meetGid == that.nowOneSeat) {
                            that.nowOneSeat = null;
                        } else if (that.meetGid == that.nowTwoSeat) {
                            that.nowTwoSeat = null;
                        }
                     }
                 } else { //移动的是未装备的将魂
                    /*根本就没有位置可以移动，直接会原来的位置*/
                    if (!that.checkMatch(myx,myy)) {
                        obj.animate({
                            'left': that.packeqPosList['pos' + that.meetPos].left + 'px',
                            'top': that.packeqPosList['pos' + that.meetPos].top + 'px'
                        },
                        100, 'ease', null);
                    } else if (that.checkMatch(myx,myy) == 1) {
                        obj.animate({
                            'left': that.unpackeqPosList['pos1'].left + 'px',
                            'top': that.unpackeqPosList['pos1'].top + 'px'
                        },
                        100, 'ease', null);
                        if (that.nowOneSeat) {
                            that.packDom.find('#gem_span' + that.nowOneSeat).animate({
                                'left': that.packeqPosList['pos' + that.meetPos].left + 'px',
                                'top': that.packeqPosList['pos' + that.meetPos].top + 'px'
                            },
                            100, 'ease', null);
                            that.packDom.find('#gem_span' + that.nowOneSeat).attr({
                                'class':'isunequip',
                                'posid': that.meetPos,
                            });
                        } else if (!that.nowOneSeat) {
                            that.refreshPackBynowChoiceId(that.meetGid);
                        }
                        that.modelDom.find('#mergegem_compose_part1').removeClass('selected').addClass('selected');
                        that.modelDom.find('#mergegem_compose_part2').removeClass('selected');
                        that.nowOneSeat = that.meetGid;
           
                        obj.attr({
                            'posid': '1',
                            'class': 'isequip'
                        });
                 
                      }else if (that.checkMatch(myx,myy) == 2) {
                        obj.animate({
                            'left': that.unpackeqPosList['pos2'].left + 'px',
                            'top': that.unpackeqPosList['pos2'].top + 'px'
                        },
                        100, 'ease', null);

                        if (that.nowTwoSeat) {
                            that.packDom.find('#gem_span' + that.nowTwoSeat).animate({
                                'left': that.packeqPosList['pos' + that.meetPos].left + 'px',
                                'top': that.packeqPosList['pos' + that.meetPos].top + 'px'
                            },
                            100, 'ease', null);
                            that.packDom.find('#gem_span' + that.nowTwoSeat).attr({
                                'class':'isunequip',
                                'posid': that.meetPos,
                            });
                        }else if(!that.nowTwoSeat) {
                            that.refreshPackBynowChoiceId(that.meetGid);
                        }
                        that.nowTwoSeat =that.meetGid;

                        that.modelDom.find('#mergegem_compose_part2').removeClass('selected').addClass('selected');
                        that.modelDom.find('#mergegem_compose_part1').removeClass('selected');
                        obj.attr({
                            'posid': '2',
                            'class': 'isequip'
                        });
                      }
                }
             });
        },

        //检测是否在背包范围
        checkPack:function(left, top) {
            if (top > -75 && top < 300 && left > -75 && left < 328) {
                return 1;
            }
            else{
                return 0;
            }
        },

        //检测是否在合成位置
        checkMatch: function(left, top) {
            if (top > this.unpackeqPosList.pos1.top - 75 && top < this.unpackeqPosList.pos1.top + 75 && left < this.unpackeqPosList.pos2.left + 75 && left > this.unpackeqPosList.pos1.left - 75) {
                if(left > this.unpackeqPosList.pos2.left - 75) {
                    return 2;
                }else if(left < this.unpackeqPosList.pos1.left + 75 && left > this.unpackeqPosList.pos1.left - 75){
                    return 1;
                }else{
                    return 0;
                }

            }else{
                return 0;
            }
        },

        //获取合成的宝石的信息
        getComposeGemInfo: function() {
            var that = this;
            if (xGame.checkLock()) {
                return false;
            }
            var Func = function(res) {

            switch (parseInt(res.status)) {
                case 1:
                    that.showCompositeAnim1(res);
                    if (that.modelDom.find('#mergegem_compose_part1').hasClass('selected') || that.modelDom.find('#mergegem_compose_part2').hasClass('selected')) {
                        that.modelDom.find('.selected').removeClass('selected');
                        that.entireDom.find('#mergegem_rt').removeClass('hasdata').addClass('nodata');
                    }else{
                        that.entireDom.find('#mergegem_rt').removeClass('hasdata').addClass('nodata');
                    }  
                    break;
                default :
                    xGame.toast.show(res.msg);
                    xGame.unlock();
                    break;
                }      
        };

            loadContents({
                'c': 'gem',
                'm': 'merge_gem',
                'gemid1': that.nowOneSeat,
                'gemid2': that.nowTwoSeat,
            }, Func);

        },

        //展示新合成的宝石
        showComposeGem: function(datas) {
            var that = this;
            var html = "";
            var data = datas.new_gem;
            html = '<span class="Mergegem_composed" style="background-image:url(' + xGame.gem(data.type ,data.quality) + ');" id="gem_span' + data.id + '"  type="' + data.quality + '" ></span>';
            that.modelDom.find('#mergegem_composed').html(html);
            html = null;
         },

        //合成宝石特效，开始
        showCompositeAnim1: function(data) {
            var that = this;
            var anim = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {left: 0, top: 0, width: that.modelDom.width(), height: that.modelDom.height()},
                'img': staticUrl + 'img/pop/gemworld_mergegemAnim1.png',
                'imgOffset': [{'left': 10, 'top': -70}, {'left': 10, 'top': -70, 'scaleX': -1}],
                'width': 290,
                'height': 300,
                'zIndex': 80,
                'loop': false,
                'endDestroy': true,
                'anim': [
                    {rect: [0, 0], interval: 90},
                    {rect: [290, 0], interval: 90},
                    {rect: [580, 0], interval: 90},
                    {rect: [870, 0], interval: 90},
                    {rect: [1160, 0], interval: 90},
                    {rect: [1450, 0], interval: 90, callback: function (){
                        that.showCompositeAnim2(data);
                    }},
                    {rect: [1740, 0], interval: 100},
                    {rect: [0, 300], interval: 100},
                    {rect: [290, 300], interval: 100},
                    {rect: [580, 300], interval: 100},
                    {rect: [870, 300], interval: 100},
                ],
                'callback': function() {   
                },
            });
            
            anim.appendTo(that.modelDom);
            anim.play();
        },

        //爆炸效果
        showCompositeAnim2: function(data){
            var that = this;
            var anim = new canvasAnimClass({
                'resource': 'all',
                'canvasOffset': {left: 0, top: -50, width: that.modelDom.width(), height: that.modelDom.height()},
                'img': staticUrl + 'img/pop/gemworld_mergegemAnim2.png',
                'imgOffset': [{'left': 15, 'top': -70}],
                'width': 290,
                'height': 300,
                'zIndex': 80,
                'loop': false,
                'endDestroy': true,
                'anim': [
                    {rect: [0, 0], interval: 100},
                    {rect: [290, 0], interval: 100},
                    {rect: [580, 0], interval: 100},
                    {rect: [870, 0], interval: 100},
                    {rect: [1160, 0], interval: 100},
                    {rect: [1450, 0], interval: 100},
                    {rect: [1740, 0], interval: 100, callback: function () {
                         that.showComposeGem(data);
                         that.packDom.find('.isequip').remove();
                    }},
                    {rect: [0, 300], interval: 80},
                    {rect: [290, 300], interval: 80},
                    {rect: [580, 300], interval: 80},
                    {rect: [870, 300], interval: 80},
                    {rect: [1160, 300], interval: 80},
                    {rect: [1450, 300], interval: 80},
                    {rect: [1740, 300], interval: 80},
                ],
                'callback': function() {
                    that.nowOneSeat = null;
                    that.nowTwoSeat = null;
                    that.nowpage = 1;
                    xGame.unlock(); 
                    that.fillMergegemPack(data);
                    miscpop.showMergegemInfo(data.new_gem, function() {
                        that.modelDom.find('#mergegem_composed').html('');
                    });
                },
            });

            anim.appendTo(that.modelDom);
            anim.play();
        },

        bindAll: function() {
            var that = this;
            that.ltDom.find('#gemworld_list').undelegate('li.item', 'click').delegate('li.item', 'click',
            function() {
                if (xGame.checkLock(true) || $(this).attr('iid') == that.siid) { //不设置属性
                    return false;
                }
                that.getItemInfoById($(this));
            });
            
            that.rtDom.find('#gem_change').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.convert();
            });

            that.rtDom.find('#gem_levelup').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.levelup();
            });

            that.rbDom.find('#gem_prev').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == 1) { //不设置属性
                    return false;
                }
                that.getPackInfo(that.page - 1);
            });

            that.rbDom.find('#gem_next').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true) || that.page == that.pages) { //不设置属性
                    return false;
                }
                that.getPackInfo(that.page + 1);
            });

            that.e.find('#show_info').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                that.e.find('#gem_detail').css('display', 'block');
                that.e.find('#gem_detail_hide').unbind('touchend').bind('touchend',
                function() {
                    that.e.find('#gem_detail').css('display', 'none');
                });
                that.modelDom.find('#mergegem_compose_part2').removeClass('selected');
                that.modelDom.find('#mergegem_compose_part1').removeClass('selected');
            });

            that.lbDom.find('#go_sanctum').undelegate('.pop_btn_small', 'touchend').delegate('.pop_btn_small', 'touchend',
            function() {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                xGame.loadWorld('sanctum');
            });

            //进入宝石合成页面
            this.lbDom.find('#gemworld_mergegem').unbind('touchend').bind('touchend',function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.fillFData();
                that.entireDom.show();
            });

            //返回按钮
            this.entireDom.find('#mergegem_hide').unbind('touchend').bind('touchend',function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.clear();
                xGame.loadWorld('gem');
            });

            //绑定模板按钮
            this.tmplDom.find('#mergegem_list').undelegate('.mergegem_item','click').delegate('.mergegem_item','click',function(e) {
                if (xGame.checkLock(true)) {
                    return false;
                }
                var id = parseInt($(this).attr('bid'));
                that.showTempInfo(id);
            });
 
            //上一页
            this.packDom.find('#mergegem_prev').unbind('touchend').bind('touchend',function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.nowpage <= 1) {
                    return false;
                }
                that.turnPages(that.nowpage - 1);
            });

            //下一页
            this.packDom.find('#mergegem_next').unbind('touchend').bind('touchend',function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.nowpage >= that.nowpages) {
                    return false;
                }
                that.turnPages(that.nowpage + 1);
            });

            //升级
            this.gemInfoDom.find('#mergegem_levelup').unbind('touchend').bind('touchend',function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.getLevelupInfo();
            });

            //转换信息
            this.gemInfoDom.find('#mergegem_change').unbind('touchend').bind('touchend',function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                that.getconvertInfo();
            });

            //绑定宝石的移动
            this.bindmergegem();

            //合成宝石
            this.entireDom.find('.mergegem_compose_btn').unbind('touchend').bind('touchend',function() {
                if (xGame.checkLock(true)) {
                    return false;
                }
                if (that.nowOneSeat && that.nowTwoSeat) {
                   that.getComposeGemInfo();
                } else{
                   xGame.toast.show(lang('gemworld_have'));
                }
            });

            //模板按钮
            
            //这里特殊处理，但没有装备的时候不能移动
            if (that.canMove != undefined) {
                that.bindGem();
            } else {
                that.rbDom.find('ul').undelegate('span', 'touchend').delegate('span', 'touchend',
                function() {
                    if (xGame.checkLock(true)) { //不设置属性
                        return false;
                    }
                    if ($(this).attr('gid') != that.sgid) {
                        that.getGemInfoById($(this));
                    }
                });
            }
        },

        bindGem: function() {
            var that = this;

            var ex = 0;
            var ey = 0;
            var ox = 0;
            var oy = 0;
            var target = null;
            
            that.rbDom.find('ul').undelegate('span', 'touchstart').delegate('span', 'touchstart',
            function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if ($(this).attr('gid') != that.sgid) {
                    that.getGemInfoById($(this));
                }

                that.touchGid = $(this).attr('gid');
                that.touchPos = $(this).attr('posid');
                /*方阵外面时就复制一个元素占着那个位置*/
                if ($(this).hasClass('isequip')) {
                    ex = parseInt(that.eqPosList['pos' + that.touchPos].left);
                    ey = parseInt(that.eqPosList['pos' + that.touchPos].top);
                } else {
                    ex = parseInt(that.uneqPosList['pos' + that.touchPos].left);
                    ey = parseInt(that.uneqPosList['pos' + that.touchPos].top);
                }

                ox = parseInt(event.touches[0].pageX);
                oy = parseInt(event.touches[0].pageY);

                $(this).addClass('ismoving');
                $(this).css('z-index', that.zIndex++);
                
                target = $(this);
            });

            that.e.unbind('touchmove').bind('touchmove',function(event) {
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                if (target) {
                    var nX = parseInt(event.touches[0].pageX);
                    var nY = parseInt(event.touches[0].pageY);

                    var newX = nX - ox;
                    var newY = nY - oy;
                    var realX = ex + newX;
                    var realY = ey + newY;
                    target.css({
                        left: realX + 'px',
                        top: realY + 'px'
                    });
                }
            });

            /*这里必须区分*/
            that.rbDom.find('ul').undelegate('span.ismoving', 'touchend').delegate('span.ismoving', 'touchend',
            function(event) {
                target = null;
                $(this).removeClass('ismoving');
                if (xGame.checkLock(true)) { //不设置属性
                    return false;
                }
                var obj = $(this);
                var myx = parseInt(obj.css('left').replace('px', ''));
                var myy = parseInt(obj.css('top').replace('px', ''));

                /*如果坐标最后都没变化，就直接不管它*/
                if (myx == ex && myy == ey) {
                    return false;
                }
                /*移动的是已装备的将魂*/
                if (obj.hasClass('isequip')) {
                    /*有位置可以移动，判断最近的位置*/
                    if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                        obj.animate({
                            'left': that.nearlyX + 'px',
                            'top': that.nearlyY + 'px'
                        },
                        100, 'ease', null);
                        /*成功就修改信息*/
                        var callback = function() {
                            obj.attr('posid', that.nearlyPosId);
                            if (that.canMove['pos' + that.nearlyPosId] == 'block') {
                                /*是空位*/
                                that.canMove['pos' + that.nearlyPosId] = that.touchGid;
                                that.canMove['pos' + that.touchPos] = 'block';
                            } else { //如果不是空位
                                var temp = that.canMove['pos' + that.nearlyPosId];
                                that.canMove['pos' + that.nearlyPosId] = that.touchGid;
                                that.canMove['pos' + that.touchPos] = temp;
                                that.rbDom.find('#gem_span' + temp).attr('posid', that.touchPos);
                                that.rbDom.find('#gem_span' + temp).animate({
                                    'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease', null).css('z-index', that.zIndex++);;
                            }
                        };
                        /*发生错误就回去*/
                        var error = function() {
                            obj.animate({
                                'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                            },
                            100, 'ease', null);
                        }
                        that.equip(that.nearlyPosId, callback, error);
                        callback = null;
                        error = null;
                    } else { //不合法，再判断有没有在背包的范围内，在范围内的话就调用卸下，不在就返回
                        if ((myx > -35 && myx < 445) && (myy > -40 && myy < 350)) {
                            var callback = function() {
                                that.canMove['pos' + that.touchPos] = 'block';

                                var size = that.rbDom.find('.block').size();
                                if (size == 0) { //如果当前页满课，直接删除
                                    obj.remove();
                                } else if (size == 12) { //全是空的，可以写死
                                    that.rbDom.find('.block').eq(0).before('<li class="gem_t' + obj.attr('type') + ' gem_ts" gid ="' + obj.attr('gid') + '" id="gemS' + obj.attr('gid') + '"></li>');
                                    that.rbDom.find('.block').eq(0).remove();
                                    obj.attr({
                                        'class': 'isunequip',
                                        'posid': 1
                                    });
                                    obj.animate({
                                        'left': that.uneqPosList['pos1'].left + 'px',
                                        'top': that.uneqPosList['pos1'].top + 'px'
                                    },
                                    100, 'ease', null);
                                } else { //没有满的话就加到队末
                                    /*底*/
                                    that.rbDom.find('.block').eq(0).before('<li class="gem_t' + obj.attr('type') + ' gem_ts" gid ="' + obj.attr('gid') + '" id="gemS' + obj.attr('gid') + '"></li>');
                                    that.rbDom.find('.block').eq(0).remove();
                                    var posid = 12 - that.rbDom.find('.block').size();
                                    obj.attr({
                                        'class': 'isunequip',
                                        'posid': posid
                                    });
                                    obj.animate({
                                        'left': that.uneqPosList['pos' + posid].left + 'px',
                                        'top': that.uneqPosList['pos' + posid].top + 'px'
                                    },
                                    100, 'ease', null);
                                }
                                

                            };
                            /*发生错误就回去*/
                            var error = function() {
                                obj.animate({
                                    'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease', null);
                            }

                            that.unequip(that.touchPos, callback, error);
                            callback = null;
                            error = null;
                        } else {
                            obj.animate({
                                'left': that.eqPosList['pos' + that.touchPos].left + 'px',
                                'top': that.eqPosList['pos' + that.touchPos].top + 'px'
                            },
                            100, 'ease', null);
                        }
                    }
                } else { //移动的是未装备的将魂
                    /*根本就没有位置可以移动，直接会原来的位置*/
                    if (that.canMove['pos1'] == undefined) {
                        obj.animate({
                            'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                            'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                        },
                        100, 'ease', null);
                    } else {
                        /*有位置可以移动，判断最近的位置*/
                        if (that.checkPos(myx, myy)) { //合法，到最近的位置上去
                            obj.animate({
                                'left': that.nearlyX + 'px',
                                'top': that.nearlyY + 'px'
                            },
                            100, 'ease', null);
                            /*成功就修改信息，右侧重新排*/
                            var callback = function() {
                                if (that.canMove['pos' + that.nearlyPosId] == 'block') { //如果是移动到空位上去
                                    that.refreshPackBySid(that.touchGid);
                                } else {
                                    var ssid = that.canMove['pos' + that.nearlyPosId]; //要跑到背包里的将魂的id
                                    var sobj = that.rbDom.find('#gem_span' + ssid); //要跑到背包里的将魂dom

                                    sobj.attr({
                                        'posid': that.touchPos,
                                        'class': 'isunequip'
                                    });
                                    sobj.animate({
                                        'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                                        'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                                    },
                                    100, 'ease', null);

                                    that.rbDom.find('#gemS' + that.touchGid).attr({
                                        'id': 'gemS' + ssid,
                                        'gid': ssid,
                                        'class': 'gem_t' + sobj.attr('type')
                                    });
                                }
                                obj.attr({
                                    'posid': that.nearlyPosId,
                                    'class': 'isequip'
                                });
                                that.canMove['pos' + that.nearlyPosId] = that.touchGid;

                            };
                            /*发生错误就回去*/
                            var error = function() {
                                obj.animate({
                                    'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                                    'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                                },
                                100, 'ease', null);
                            }
                            that.equip(that.nearlyPosId, callback, error, true);
                            callback = null;
                            error = null;
                        } else { //不合法，回到原位置
                            obj.animate({
                                'left': that.uneqPosList['pos' + that.touchPos].left + 'px',
                                'top': that.uneqPosList['pos' + that.touchPos].top + 'px'
                            },
                            100, 'ease', null);
                        }
                    }
                }
            });
        },

        refreshPackBySid: function(gid) {
            /*删除这个元素*/
            var opos = parseInt(this.rbDom.find('#gem_span' + gid).attr('posid')); //原来的位置ID
            this.rbDom.find('#gemS' + gid).remove();

            /*li底图的移动*/
            if (this.rbDom.find('.block').size() == 0) {
                if (this.rbDom.find('.closed').size() != 0) {
                    this.rbDom.find('.closed').first().before('<li class="block"></li>');
                } else {
                    this.rbDom.find('ul').append('<li class="block"></li>');
                }
            } else {
                this.rbDom.find('.block').last().after('<li class="block"></li>');
            }
            /*span的更新*/
            var pos;
            for (var i = 1; i <= this.rbDom.find('.isunequip').size(); i++) {
                /*这里的顺序要小心，否则就会出错，不能用eq(i),因为顺序是会变的*/
                pos = parseInt(this.rbDom.find('.isunequip').eq(i - 1).attr('posid'));
                if (pos > opos) {
                    this.rbDom.find('.isunequip').eq(i - 1).css({
                        'left': this.uneqPosList['pos' + (pos - 1)].left + 'px',
                        'top': this.uneqPosList['pos' + (pos - 1)].top + 'px'
                    }).attr('posid', (pos - 1));
                }
            }
            opos = pos = null;
        },

        /*转宝石*/
        convert: function(confirm) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 2 : xGame.toast.show(lang('gemworld_get_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('gemworld_turn_fail'));
                    break;
                case 1:
                    if (confirm == undefined) {
                        xGame.confirm.show(lang('gemworld_turn_part'), lang('gemworld_turn_part_ask', [res.chip]), 2,
                        function() {
                            that.convert(1);
                            xGame.confirm.hide();
                        },
                        function() {
                            xGame.confirm.hide();
                        });
                    } else {
                        that.fillCurrentGem(res.all_chip);
                        /*如果要转的将魂在背包里*/
                        var obj = that.rbDom.find('#gem_span' + that.sgid);
                        
                        if (obj.hasClass('isunequip')) { //如果要转的将魂在背包里
                            var pos = parseInt(obj.attr('posid'));
                            var gid = that.rbDom.find('.isunequip[posid="' + (pos + 1) + '"]').attr('gid');

                            if (gid == undefined) { //先看后面的，再看前一个
                                gid = that.rbDom.find('.isunequip[posid="' + (pos - 1) + '"]').attr('gid');
                                if (gid == undefined) {
                                    that.rtDom.attr('class', 'nodata');
                                } else {
                                    /*特殊处理，解锁了才能请求*/
                                    xGame.unlock();
                                    that.getGemInfoById(that.rbDom.find('#gem_span' + gid));
                                }
                            } else {
                                xGame.unlock();
                                that.getGemInfoById(that.rbDom.find('#gem_span' + gid));
                            }
                            that.refreshPackBySid(that.sgid);
                            pos = null;
                            gid = null;
                        } else { //如果要转的将魂在身上里
                            that.canMove['pos' + obj.attr('posid')] = 'block';
                            that.rtDom.attr('class', 'nodata');
                            that.lbDom.find('.selected').removeClass('selected');
                        }
                        obj.remove();
                    }
                    break;
                case 2:
                    xGame.toast.show(lang('gemworld_lv_140'));
                    break;
                default:
                    xGame.toast.show(lang('exchange_original_soul_fail'));
                    break;
                }

                xGame.unlock();

            };
            if (confirm == undefined) {
                loadContents({
                    'c': 'gem',
                    'm': 'gem_turn',
                    'id': that.sgid
                },
                Func);
            } else {
                loadContents({
                    'c': 'gem',
                    'm': 'gem_turn',
                    'id': that.sgid,
                    'confirm': confirm
                },
                Func);
            }
            Func = null;
        },

        /*装备宝石*/
        equip: function(site, callback, error, refreshPack) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 7 : xGame.toast.show(lang('gemworld_type_not_fit'));
                    break;
                case - 6 : xGame.toast.show(lang('gemworld_get_type_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('gemworld_site_no_change'));
                    break;
                case - 4 : xGame.toast.show(lang('gemworld_gem_had_equiped'));
                    break;
                case - 3 : xGame.toast.show(lang('gemworld_get_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('beatengod_get_item_info_fail'));
                    break;
                case - 1 : xGame.toast.show(lang('gemworld_site_error'));
                    break;
                case 2:
                    xGame.toast.show(lang('gemworld_lv_140'));
                    break;
                case 1:
                    callback();
                    that.page = parseInt(res.page);
                    that.pages = parseInt(res.allpage);
                    
                    if (refreshPack) {
                        that.fillRb(res, true);
                    }
                    that.lbDom.find('.selected').removeClass('selected');
                    that.lbDom.find('#gempos' + site).addClass('selected');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                if (res.status != 1) {
                    error();
                }
                xGame.unlock();

            };
            loadContents({
                'c': 'gem',
                'm': 'adron',
                'id': that.touchGid,
                'eid': that.siid,
                'site': site
            },
            Func);
            Func = null;
        },

        /*卸下宝石*/
        unequip: function(site, callback, error) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 3 : xGame.toast.show(lang('bag_is_full'));
                    break;
                case - 2 : xGame.toast.show(lang('gemworld_gem_is_unequiped'));
                    break;
                case - 1 : xGame.toast.show(lang('gemworld_not_exist'));
                    break;
                case 2:
                    xGame.toast.show(lang('gemworld_lv_140'));
                    break;
                case 1:
                    callback();
                    //卸下的时候更新页码
                    that.page = parseInt(res.page);
                    that.pages = parseInt(res.allpage);
                    that.fillRb(res, true);
                    that.lbDom.find('.selected').removeClass('selected');
                    that.rbDom.find('ul').find('#gemS' + that.sgid).addClass('gem_ts');
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }
                if (res.status != 1) {
                    error();
                }
                xGame.unlock();

            };
            loadContents({
                'c': 'gem',
                'm': 'pick_off',
                'id': that.touchGid
            },
            Func);
            Func = null;
        },

        /*获得装备信息*/
        getItemInfoById: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var gid = that.rbDom.find('ul').find('li').eq(0).attr('gid');

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 2 : xGame.toast.show(lang('sanctumworld_lv_140'));
                    break;
                case - 1 : xGame.toast.show(lang('equip_not_exist'));
                    break;
                case 1:
                    that.ltDom.find('#gemworld_list').find('#itemid' + that.siid).removeClass('selected');
                    obj.addClass('selected');
                    
                    that.siid = res.equip.id;
                    that.sgid = res.gem.id;
                    
                    that.lbDom.find('.selected').removeClass('selected');
                    that.fillLb(res.equip);
                    that.fillRt(res.gem);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c': 'gem',
                'm': 'get_gem_by_id',
                'id': obj.attr('iid'),
                'gemid': gid
            },
            Func);
            gid = null;
            Func = null;
        },

        /*这个功能的作用是传进参数，检测边界，
         * 返回true时代表有位置可以到达，再设置that.nearlyX和that.nearlyY完成操作；
         * 返回false时就代表直接返回原来的位置
         * 通过计算得知x间隔为133，y间隔为117*/
        checkPos: function(x, y) {
            var that = this;

            /*结果集*/
            var result = [];
            that.nearlyX = that.nearlyY = that.nearlyPosId = 0;

            /*第一步，告诉程序有几个点可以移动，要排除自己*/
            ////console.log(that.canMove);
            for (var i = 1; i < 9; i++) {
                if (that.canMove['pos' + i] != that.touchGid && that.canMove['pos' + i] != undefined) {
                    result.push({
                        'name': 'pos' + i,
                        'id': that.eqPosList['pos' + i].id,
                        'distance': that.getDistance(Math.abs(that.eqPosList['pos' + i].left - x), Math.abs(that.eqPosList['pos' + i].top - y))
                    });
                }
            }
            /*按属性拍大小，方法写在base.js里面*/
            result.sort(helper.compare("distance"));
            if (result.length != 0) {
                that.nearlyPosId = result[0]['id'];
                /*不管如何都返回最近的x，y*/
                that.nearlyX = that.eqPosList[result[0]['name']].left;
                that.nearlyY = that.eqPosList[result[0]['name']].top;

                var myDistance = that.getDistance(Math.abs(that.nearlyX - x), Math.abs(that.nearlyY - y));

                if (myDistance < that.distance / 2) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }

        },

        getDistance: function(x, y) {
            return Math.sqrt(x * x + y * y);
        },

        /*获得宝石信息*/
        getGemInfoById: function(obj) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 2 : xGame.toast.show(lang('gemworld_lv_140'));
                    break;
                case - 1 : xGame.toast.show(lang('gemworld_not_exist'));
                    break;
                case 1:
                    that.rbDom.find('#gemS' + that.sgid).removeClass('gem_ts');
                    that.sgid = obj.attr('gid');
                    that.rbDom.find('#gemS' + that.sgid).addClass('gem_ts');
                    
                    that.lbDom.find('.selected').removeClass('selected');
                    that.fillRt(res.gem);
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c': 'gem',
                'm': 'get_gem_info',
                'id': obj.attr('gid')
            },
            Func, true);
            Func = null;
        },

        /*宝石升级*/
        levelup: function() {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;
            var Func = function(res) {

                switch (parseInt(res.status)) {
                case - 6 : xGame.toast.show(lang('level_up_fail'));
                    break;
                case - 5 : xGame.toast.show(lang('gemworld_part_not_enough'));
                    break;
                case - 4 : xGame.toast.show(lang('gemworld_get_tmpl_fail'));
                    break;
                case - 3 : xGame.toast.show(lang('gemworld_get_lvup_info_fail'));
                    break;
                case - 2 : xGame.toast.show(lang('gemworld_gem_lv_full'));
                    break;
                case - 1 : xGame.toast.show(lang('gemworld_get_info_fail'));
                    break;
                case 1:
                    res.all_chip = parseInt(res.all_chip);
                    res.need_chip = parseInt(res.need_chip);
                    that.fillCurrentGem(res.all_chip);
                    that.rtDom.find('#gem_abnow').find('em').html(res.power);
                    that.rtDom.find('#gem_level').html('Lv.' + res.level).css('color', helper.getItemColor(res.quality));
                    that.rtDom.find('#gem_name').css('color', helper.getItemColor(res.quality));
                    if (res.ismax == 0) {
                        that.rtDom.find('#gem_abnext').html('(' + lang('next_lv') + ' +' + res.next_power + ')');
                        that.rtDom.find('#gem_lvneed').html(res.need_chip);
                        if (res.need_chip > res.all_chip) {
                            that.rtDom.find('#gem_lvneed').attr('class', 'buzu');
                        }
                        that.rtDom.find('.gem_lvneed').css('display', 'block');
                    } else {
                        that.rtDom.find('#gem_levelup').css('display', 'none');
                        that.rtDom.find('#gem_abnext').html(lang('full_lv'));
                        that.rtDom.find('.gem_lvneed').css('display', 'none');
                    }
                    var btype = that.rtDom.find('#selected_gem').attr('btype');
                    that.rtDom.find('#selected_gem').attr('class', 'gem_t' + res.quality + ' gem_ts').find('span').css('background-image', 'url(' + xGame.gem(btype, res.quality) + ')');
                    that.rbDom.find('.gem_ts').attr('class', 'gem_t' + res.quality + ' gem_ts');
                    that.rbDom.find('#gem_span' + that.sgid).attr('type', res.quality).css('background-image', 'url(' + xGame.gem(btype, res.quality) + ')');
                    btype = null;
                    
                    var obj = that.rbDom.find('#gem_span' + that.sgid);
                    var posid = obj.attr('posid');
                    if (obj.hasClass('isunequip')) {
                        that.lvupAnim(that.uneqPosList['pos' + posid].left, that.uneqPosList['pos' + posid].top);
                    } else {
                        that.lvupAnim(that.eqPosList['pos' + posid].left, that.eqPosList['pos' + posid].top);
                    }
                    
                    break;
                case 2:
                    xGame.toast.show(lang('gemworld_lv_140'));
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c': 'gem',
                'm': 'level_up',
                'id': that.sgid
            },
            Func);
            Func = null;
        },

        showPage: function() {
            this.rbDom.find('#gem_page_num').html(this.page + '/' + this.pages);
        },

        /*翻页*/
        getPackInfo: function(page) {
            if (xGame.checkLock()) {
                return false;
            }
            var that = this;

            var Func = function(res) {

                switch (parseInt(res.status)) {
                case 2 : xGame.toast.show(lang('gemworld_lv_140'));
                    break;
                case - 1 : xGame.toast.show(lang('gemworld_page_not_exist'));
                    break;
                case 1:
                    that.page = parseInt(res.page);
                    that.pages = parseInt(res.allpage);
                    that.fillRb(res, true);
                    var judge = true;
                    for(var i = 0; i < 9; i++){
                        if(that.lbDom.find('#gempos'+ i +'').hasClass('selected')){  
                           judge = false;
                        }
                    }
                    if (judge) {
                        that.rtDom.attr('class', 'nodata');
                        that.sgid = null;
                    } 
                    break;
                default:
                    xGame.toast.show(lang('undefined_error'));
                    break;
                }

                xGame.unlock();

            };
            loadContents({
                'c': 'gem',
                'm': 'get_gem_by_page',
                'p': page
            },
            Func);
            Func = null;
        },

        fillLb: function(data) {

            this.lbDom.find('#item_name').html(data.name);
            this.lbDom.find('#item_img').css('backgroundImage', 'url(' + xGame.itemurl(data.equipid) + ')');

            
            /*每次获得将领信息都要初始化一下canMove*/
            this.canMove = {};
            this.rbDom.find('ul').find('.isequip').remove();

            var html = '';
            for (var i = 1; i <= 8; i++) {
                this.lbDom.find('#gemlpos' + i).html('');
                if (data[i] != 0) {
                    this.canMove['pos' + i] = data[i].id;
                    html += '<span class="isequip" style="background-image:url(' + xGame.gem(data[i].type ,data[i].quality) + ');' + this.eqPosList['pos' + i].style + '" id="gem_span' + data[i].id + '" posid="' + i + '" gid="' + data[i].id + '" type="' + data[i].quality + '" ' + '><em>' + helper.getTalent(data[i].type) + '</em></span>';
                } else {
                    this.canMove['pos' + i] = 'block';
                }
            }
            if (html != '') {
                this.rbDom.find('ul').append(html);
            }
            html = null;
        },

        fillRb: function(data, isRebuild) {
            this.fillCurrentGem(data.chip);
            data.num = parseInt(data.num);
            
            if (this.page == 1) {
                //第一页的时候针对下一页按钮做特殊处理
                if (this.page == this.pages) {
                    this.rbDom.find('#gem_next').css('display', 'none');
                } else {
                    this.rbDom.find('#gem_next').css('display', 'block');
                }
                this.rbDom.attr('class', 'isfirst');
            } else if (this.page == this.pages) {
                this.rbDom.attr('class', 'islast');
            } else {
                this.rbDom.removeClass();
            }
            
            this.showPage();
            var html = '';
            var html1 = '';
            for (var i = 0; i < 12; i++) {
                if (i + 1 + (data.page - 1) * 12 <= data.num) {
                    if (data.list[i] != undefined) {
                        html += '<li class="gem_t' + data.list[i].quality + '" gid="' + data.list[i].id + '" id="gemS' + data.list[i].id + '"></li>';
                        html1 += '<span class="isunequip" style="background-image:url(' + xGame.gem(data.list[i].type , data.list[i].quality) + ');' + this.uneqPosList['pos' + (i + 1)].style + '" id="gem_span' + data.list[i].id + '" posid="' + (i + 1) + '" gid="' + data.list[i].id + '" type="' + data.list[i].quality + '" ><em>' + helper.getTalent(data.list[i].type) + '</em></span>';
                    } else {
                        html += '<li class="block"></li>';
                    }
                } else {
                    html += '<li class="block"></li>';
                }
            }
            if (isRebuild == undefined) {
                this.rbDom.find('ul').html(html + html1);
            } else {
                this.rbDom.find('ul').find('li').remove();
                this.rbDom.find('ul').find('.isunequip').remove();
                this.rbDom.find('ul').append(html + html1);
            }

            html = html1 = null;
        },

        fillCurrentGem: function(num) {
            if (num == undefined) {
                return false;
            }
            var that = this;

            if (that.leftGemNum != num) {
                //that.rbDom.find('#gem_own').html(num);
                xGame.doAnimate2(that.rbDom.find('#gem_own'), 'changcolor', 100, 7,
                function() {
                    if (that.rbDom) {
                        that.rbDom.find('#gem_own').html(num);
                    }
                });
                that.leftGemNum = parseInt(num);
            } else {
                that.rbDom.find('#gem_own').html(num);
            }
        },

        fillRt: function(data) {

            if (data.id == undefined) {
                this.rtDom.attr('class', 'nodata');
                return false;
            }
            
            if (parseInt(data.site) > 0) {
                this.lbDom.find('#gempos' + data.site).addClass('selected');
            }
            
            data.need_chip = parseInt(data.need_chip);
            this.rtDom.attr('class', 'hasdata');
            this.rbDom.find('ul').find('.gem_ts').removeClass('gem_ts');
            /*貌似这段代码是没用了
            if (data.from != undefined && data.from > 8) {
                this.rbDom.find('ul').find('li').eq(0).addClass('gem_ts');
            }
            */

            if (data.ismax == 0) {
                this.rtDom.find('#gem_levelup').css('display', 'block');
                this.rtDom.find('#gem_abnow').html(lang('soul_properties') + ':' + helper.getTalent(parseInt(data.type)) + '+<em>' + data.power + '</em><em id="gem_abnext">(' + lang('next_lv') + '+' + data.next_power + ')</em>');
                this.rtDom.find('#gem_lvneed').html(data.need_chip);
                this.rtDom.find('.gem_lvneed').css('display', 'block');
            } else {
                this.rtDom.find('#gem_levelup').css('display', 'none');
                this.rtDom.find('#gem_abnow').html(lang('soul_properties') + ':' + helper.getTalent(parseInt(data.type)) + '+<em>' + data.power + '</em><em id="gem_abnext">' + lang('full_lv') + '</em>');
                this.rtDom.find('.gem_lvneed').css('display', 'none');
            }

            this.rbDom.find('ul').find('#gemS' + this.sgid).removeClass('gem_ts');
            this.sgid = data.id;
            this.rbDom.find('ul').find('#gemS' + this.sgid).addClass('gem_ts');

            this.rtDom.find('#gem_name').html(data.name + '<em id="gem_level" style="color:' + helper.getItemColor(data.quality) + ';" >Lv.' + data.level + '</em>').css('color', helper.getItemColor(data.quality));
            this.rtDom.find('#selected_gem').attr({
                'class': 'gem_t' + data.quality + ' gem_ts',
                'btype': data.type
            }).find('span').css('background-image', 'url(' + xGame.gem(data.type, data.quality) + ')');

            if (data.need_chip > this.leftGemNum) {
                this.rtDom.find('#gem_lvneed').attr('class', 'buzu');
            } else {
                this.rtDom.find('#gem_lvneed').removeClass('buzu');
            }
        },
        
        lvupAnim: function(x,y) {
            var that = this;
            var movieclip;

            //创建动画底图
            var mc = new movieClipClass({
                "img": ximg(staticUrl + 'img/pop/gemworld_lvup.png'),
                "width": 150,
                "height": 250,
                "x": x - 40,
                "y": y - 130,
                "loop": false,
                "endDestory": true,
                "frames": [{
                    rect: [0, 0],
                    interval: 40
                },
                {
                    rect: [150, 0],
                    interval: 40
                },
                {
                    rect: [300, 0],
                    interval: 40
                },
                {
                    rect: [450, 0],
                    interval: 40
                },
                {
                    rect: [6000, 0],
                    interval: 40
                }]
            });
            //开始播放
            mc.appendTo(this.rbDom);
            mc.play();
            mc = null;
        },
        
        unbindAll: function () {

            //后加
            if (this.tmplDom) {
                this.tmplDom.find('#mergegem_list').undelegate('.mergegem_item','touchend');
                this.tmplDom = null;
            }
            if (this.packDom) {
                this.packDom.find('#mergegem_prev').unbind('touchend');
                this.packDom.find('#mergegem_next').unbind('touchend');
                this.packDom.undelegate('span','touchstart');
                this.packDom.find('ul').undelegate('span.ismoving', 'touchend');
                this.packDom = null;
            }
            if (this.gemInfoDom) {
                this.gemInfoDom.find('#mergegem_levelup').unbind('touchend');
                this.gemInfoDom.find('#mergegem_change').unbind('touchend');
                this.gemInfoDom = null;
            }
            if (this.modelDom) {
                this.modelDom = null;
            }
            if (this.entireDom) {
                this.entireDom.find('#mergegem_hide').unbind('touchend');
                this.entireDom.find('.mergegem_compose_btn').unbind('touchend');
                this.entireDom.unbind('touchmove');
                this.entireDom.remove();
                this.entireDom = null;
            }
           
            if (this.e) {
                this.e.unbind('touchmove');
                this.e.find('#gemworld_hide').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
            if (this.ltDom) {
                this.ltDom.find('#gemworld_list').undelegate('li.item', 'click');
                this.ltDom.remove();
                this.ltDom = null;
            }
            if (this.rtDom) {
                this.rtDom.find('#gem_change').undelegate('.pop_btn_small', 'touchend');
                this.rtDom.find('#gem_levelup').undelegate('.pop_btn_small', 'touchend');
                this.rtDom.remove();
                this.rtDom = null;
            }
            if (this.rbDom) {
                this.rbDom.find('#gem_prev').undelegate('.pop_btn_small', 'touchend');
                this.rbDom.find('#gem_next').undelegate('.pop_btn_small', 'touchend');
                this.rbDom.find('ul').undelegate('span.ismoving', 'touchend');
                this.rbDom.find('ul').undelegate('span', 'touchend');
                this.rbDom.find('ul').undelegate('span', 'touchstart');
                this.rbDom.remove();
                this.rbDom = null;
            }
            if (this.lbDom) {
                this.lbDom.find('#show_info').undelegate('.pop_btn_small', 'touchend');
                this.lbDom.find('#go_sanctum').undelegate('.pop_btn_small', 'touchend');
                this.lbDom.find('#go_tower').undelegate();
                this.lbDom.find('#gemworld_mergegem').unbind('touchend');
                this.lbDom.remove();
                this.lbDom = null;
            }
        },

        clear: function() {
            xGame.unlock();
            if (this.e == undefined) {
                return false;
            }

            this.siid = this.sgid = this.leftGemNum = this.uneqPosList = this.eqPosList = null;
            this.zIndex = this.touchGid = this.touchPos = this.canMove = this.nearlyX = null;
            this.nearlyY = this.nearlyPosId = this.distance = this.page = this.pages = null;
            if (this.gemScroller) {
                this.gemScroller.destroy();
                delete this.gemScroller;
            }
            if (this.templetTime) {
                clearTimeout(this.templetTime);
                this.templetTime = null;
            }
            if (this.mergegemScroll) {
                this.mergegemScroll.destroy();
                this.mergegemScroll = null;
            }
            this.mergegemData = null;
            this.nowChoiceTemp = 1;
            this.nowpages = 1;
            this.nowpage = 1;
            this.GemleftNum = 0;
            this.packeqPosList = null;
            this.nowChoiceId = null;
            this.meetGid = null;
            this.meetPos = null;
            this.nowOneSeat = null;
            this.nowTwoSeat = null;
            this.templetTime = null;
            
            this.unbindAll();
        }
    };
})();