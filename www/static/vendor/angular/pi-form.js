(function(window, angular) {'use strict';
  var form = angular.module('piForm', []);
  form.directive('piUnique', function($http, $q) {
    var link = function (scope, element, attr, ctrl) {
      var url = attr.piUnique;
      var params = {};
      var deferred;
      if (!url) {
        throw new Error('Please set url value on pi-unique attribute');
      }
      scope.$watch(attr.ngModel, function(value) {
        if(ctrl.$pristine) return;
        if(deferred) deferred.resolve();
        deferred = $q.defer();
        params[attr.name] = value;
        $http.get(url, {
          cache: true,
          params: params,
          timeout: deferred.promise
        }).success(function(data) {
          ctrl.$setValidity('unique', !data.status);
        });
      });
    };
    return {
      require: 'ngModel',
      link: link,
      restrict: 'A'
    }
  }).directive('piMatch', function() {
    var link = function(scope, element, attr, ctrl) {
        var match = element.inheritedData('$formController')[attr.piMatch];
        ctrl.$parsers.push(function(value) {
          if (value == match.$viewValue) {
            ctrl.$setValidity("mismatch", true);
            return value;
          } else {
            ctrl.$setValidity("mismatch", false);
            return undefined;
          }
        });
        match.$parsers.push(function(value) {
          var val = ctrl.$viewValue;
          ctrl.$setValidity("mismatch", value == val);
          return value;
        });
    };
    return {
        require: 'ngModel',
        link: link,
        restrict: 'A'
    };
  });
})(window, window.angular);
