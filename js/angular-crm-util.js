/// crmUi: Sundry UI helpers
(function (angular, $, _) {
  angular.module('crmUtil', []);

  // Adapter for CRM.status which supports Angular promises (instead of jQuery promises)
  // example: crmStatus('Saving', crmApi(...)).then(function(result){...})
  angular.module('crmUtil').factory('crmStatus', function($q){
    return function(options, aPromise){
      return CRM.toAPromise($q, CRM.status(options, CRM.toJqPromise(aPromise)));
    };
  });

  angular.module('crmUtil').factory('crmNow', function($q){
    // FIXME: surely there's already some helper which can do this in one line?
    // @return string "YYYY-MM-DD hh:mm:ss"
    return function crmNow() {
      var currentdate = new Date();
      var yyyy = currentdate.getFullYear();
      var mm = currentdate.getMonth() + 1;
      mm = mm < 10 ? '0' + mm : mm;
      var dd = currentdate.getDate();
      dd = dd < 10 ? '0' + dd : dd;
      var hh = currentdate.getHours();
      hh = hh < 10 ? '0' + hh : hh;
      var min = currentdate.getMinutes();
      min = min < 10 ? '0' + min : min;
      var sec = currentdate.getSeconds();
      sec = sec < 10 ? '0' + sec : sec;
      return yyyy + "-" + mm + "-" + dd + " " + hh + ":" + min + ":" + sec;
    };
  });

  // crmWatcher allows one to setup event listeners and temporarily suspend
  // them en masse.
  //
  // example:
  // angular.controller(... function($scope, crmWatcher){
  //   var watcher = crmWatcher();
  //   function myfunc() {
  //     watcher.suspend('foo', function(){
  //       ...do stuff...
  //     });
  //   }
  //   watcher.setup('foo', function(){
  //     return [
  //       $scope.$watch('foo', myfunc),
  //       $scope.$watch('bar', myfunc),
  //       $scope.$watch('whiz', otherfunc)
  //     ];
  //   });
  // });
  angular.module('crmUtil').factory('crmWatcher', function(){
    return function() {
      var unwatches = {}, watchFactories = {}, suspends = {};

      // Specify the list of watches
      this.setup = function(name, newWatchFactory) {
        watchFactories[name] = newWatchFactory;
        unwatches[name] = watchFactories[name]();
        suspends[name] = 0;
        return this;
      };

      // Temporarily disable watches and run some logic
      this.suspend = function(name, f) {
        suspends[name]++;
        this.teardown(name);
        var r;
        try {
          r = f.apply(this, []);
        } finally {
          if (suspends[name] === 1) {
            unwatches[name] = watchFactories[name]();
            if (!angular.isArray(unwatches[name])) {
              unwatches[name] = [unwatches[name]];
            }
          }
          suspends[name]--;
        }
        return r;
      };

      this.teardown = function(name) {
        if (!unwatches[name]) return;
        _.each(unwatches[name], function(unwatch){
          unwatch();
        });
        delete unwatches[name];
      };

      return this;
    }
  });

  // example: scope.$watch('foo', crmLog.wrap(function(newValue, oldValue){ ... }));
  angular.module('crmUtil').factory('crmLog', function(){
    var level = 0;
    var write = console.log;
    function indent() {
      var s = '>';
      for (var i = 0; i < level; i++) s = s + '  ';
      return s;
    }
    var crmLog = {
      log: function(msg, vars) {
        write(indent() + msg, vars);
      },
      wrap: function(label, f) {
        return function(){
          level++;
          crmLog.log(label + ": start", arguments);
          var r;
          try {
            r = f.apply(this, arguments);
          } finally {
            crmLog.log(label + ": end");
            level--;
          }
          return r;
        }
      }
    };
    return crmLog;
  });

})(angular, CRM.$, CRM._);
