/*国战战报*/
(function() {
    countrywarnoticePopClass = function() {
        this.e;
        this.isshow = false;
    };

    countrywarnoticePopClass.prototype = {
        init: function() {
            var that = this;

            if(that.isshow) {
                $('#worldloading').hide();
                return;
            }

            var Func = function(res) {
                
                if (res.status == '2') {
                    xGame.toast.show(lang('lv_not_enough'));
                    $('#worldloading').hide();

                    return false;

                } else if (res.status == '1' && res.log) {
                    that.e = xGame.loadPopTmpl('countrywarnotice');
                    
                    that.e.find('.hidebtn').unbind('touchend').bind('touchend',
                    function(e) {
                        if (xGame.checkLock(true)) {
                            return false;
                        }
                        countrywarnoticepop.hide();
                        e.preventDefault();
                    });
                    that.fillList(res.log);

                } else {
                    xGame.toast.show(lang('undefined_error'));
                    $('#worldloading').hide();

                    return false;
                }
            };
            
            //加载数据
            loadContents({
                'c': 'siege',
                'm': 'siege_notice'
            },
            Func, true);
            Func = null;
        },

        //填充战报列表
        fillList: function(log) {
            var html = '';
            
            var len = log.length;
            for(var i = 0; i < len; i++) {
                var win_type2 = log[i].win_type == 1 ? 0 : 1;
                html += '<li class="countrywarnotice_list_li">';                
                
                if(log[i].city == 1 || log[i].city == 2 || log[i].city == 4 || log[i].city == 5) {
                    html += '<span class="city_img small">' + log[i].city_name + '</span>';                
                } else {
                    html += '<span class="city_img">' + log[i].city_name + '</span>';
                }

                html += '<span class="countrywarnotice_attack" cid="' + log[i].attack_country + '">' + lang('countrywarnotice_attack') + '<em>【' + log[i].attack_country_name + '】</em><em class="countrywarnotice_win' + log[i].win_type + '"></em></span>';
                html += '<span class="countrywarnotice_guard" cid="' + log[i].country + '">' + lang('countrywarnotice_guard') + '<em>【' + log[i].country_name + '】</em><em class="countrywarnotice_win' + win_type2 + '"></em></span>';
                html += '</li>';
            }

            this.e.find('#countrywarnotice_list').html(html);
            html = null;
            
            this.myScroller = new iScroll('countrywarnotice_scroller', {
                bounce: false,
                momentum: false,
                hScrollbar: false,
                vScrollbar: false
            });

            this.myScroller.refresh();
            this.e.show();
            $('#worldloading').hide();
            this.isshow = true;
        },

        unbindAll: function () {
            if (this.e) {
                this.e.find('.hidebtn').unbind('touchend');
                this.e.remove();
                this.e = null;
            }
        },

        hide: function() {
            xGame.unlock();
            this.isshow = false;
            

            if (this.myScroller != null) {
                this.myScroller.destroy();
                this.myScroller = null;
            }
            this.unbindAll();
        }
    }
})();