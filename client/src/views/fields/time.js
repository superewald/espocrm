define('views/fields/time', ['views/fields/base'], function (Dep) {

    /**
     * JSDoc enabling code completion in PhpStorm/Webstorm.
     *
     * @class
     * @name Class
     * @extends modules:views/fields/base.Class
     * @memberOf modules:custom:views/fields/time
     */
    return Dep.extend(/** @lends modules:custom:views/fields/time.Class# */{
        type: 'time',

        detailTemplate: 'fields/time/detail',

        editTemplate: 'fields/time/edit',

        timeFormatMap: {
            'HH:mm': 'H:i',
            'hh:mm A': 'h:i A',
            'hh:mm a': 'h:i a',
            'hh:mmA': 'h:iA',
            'hh:mma': 'h:ia',
        },

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        data: function() {
            var data = Dep.prototype.data.call(this);

            data.time = this.model.get(this.name);

            return data;
        },

        initTimePicker: function() {
            var $time = this.$time;

            $time.timepicker({
                step: this.params.minuteStep || 30,
                scrollDefaultNow: true,
                timeFormat: this.timeFormatMap[this.getDateTime().timeFormat],
                disableTimeRanges: this.getDisabledTimeRanges(),
                minTime: this.params.min || '00:00',
                maxTime: this.params.max || '23:59',
                wrapHours: this.params.wrapHours || false
            });

            $time
                .parent()
                .find('button.time-picker-btn')
                .on('click', () => {
                    $time.timepicker('show');
                });
        },

        timeStringToOffset: function(timeString) {
            let hourMod = timeString.toLowerCase().endsWith('pm') ? 2 : 1;

            let timeSplit = timeString.toLowerCase().replace('am', '').replace('pm', '').split(':');
            if(timeSplit.length == 1) {
                return (timeSplit[0] * hourMod) * 3600;
            } else if(timeSplit.length == 2) {
                return ((timeSplit[0] * hourMod) * 3600) + (timeSplit[1] * 60);
            }

            return 0;
        },

        isTimeDisabled: function(offset) {
            let min = this.params.min !== null ? this.timeStringToOffset(this.params.min) : 0;
            let max = this.params.max !== null ? this.timeStringToOffset(this.params.max) : 86400;

            if(offset < min || offset > max)
                return true;
            
            let disabledTimes = this.getDisabledTimeRanges();
            for(const disabled of disabledTimes) {
                if(offset >= disabled[0] && offset <= disabled[1])
                    return true;
            }
            
            return false;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            var $time = this.$time = this.$el.find('input.time');
            var previousValue = $time.val();

            if(this.mode === 'edit') {
                
                this.initTimePicker();
                
                let timeout = false;
                let isTimeFormatError = false;

                $time.on('change', (e) => {
                    if (!timeout) {
                        if (isTimeFormatError) {
                            $time.val(previousValue);
                            return;
                        }

                        if (this.noneOption && $time.val() === '') {
                            $time.val(this.noneOption);
                            return;
                        }
                        
                        let timeOffset = this.timeStringToOffset($time.val());
                        if(this.isTimeDisabled(timeOffset)) {
                            var msg = this.translate('timeOutOfRange', 'messages', 'Admin')
                                .replace('{time}', $time.val())
                                .replace('{field}', this.name);
                            Espo.Ui.notify(msg, 'error', 2500);
                            $time.val(previousValue);
                            return;
                        }

                        this.trigger('change');

                        previousValue = $time.val();
                    }

                    timeout = true;

                    setTimeout(() => timeout = false, 100);
                });

                $time.on('timeFormatError', () => {
                    isTimeFormatError = true;

                    setTimeout(() => isTimeFormatError = false, 50);
                });
            }
        },

        getDisabledTimeRanges: function() {
            let param = this.params.disabledHours || [];
            let disabledRanges = [];

            param.forEach((range) => {
                let start = end = 0;

                if(range.indexOf('-') === -1) {
                    let timeSplit = range.split(':');
                    if(timeSplit.length == 2) {
                        start = this.timeStringToOffset(range);
                        end = this.timeStringToOffset(timeSplit[0]) + ((parseInt(timeSplit[1]) + 1) * 60);
                    } else if(timeSplit.length == 1) {
                        start = this.timeStringToOffset(range);
                        end = start + 3600;
                    }
                } else {
                    let rangeSplit = range.split('-');
                    start = this.timeStringToOffset(rangeSplit[0]);
                    end = this.timeStringToOffset(rangeSplit[1]);
                }

                disabledRanges.push([start, end]);
            });

            return disabledRanges;
        },

        fetch: function() {
            var data = {};

            data[this.name] = this.$time.val();

            return data;
        }
    });
});