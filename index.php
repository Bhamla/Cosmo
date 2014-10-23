<? include 'core/app/initialize.php'; ?>
<!doctype html>
<html xmlns:ng="http://angularjs.org" id="ng-app" ng-app="main" ng-controller="HTMLCtrl">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <!--[if lte IE 9]>
            <script>
                if(window.location.href.indexOf('#!') === -1)
                    window.location.href = window.location.protocol + '//' + window.location.host + '/#!' + window.location.pathname;
            </script>
            <script src="core/js/3rd-party/html5.js"></script>
        <![endif]-->
        <!--[if lte IE 8]>
            <script src="core/js/3rd-party/json2.js"></script>
            <br /><br />Your broswer is incompatible with this site. Please upgrade to a <a href="http://www.browsehappy.com">newer browser.</a>
        <![endif]-->
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0"/>
        <meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
        <!-- Meta Tags -->
        <title ng-bind-template="{{title}}"><?php echo $content['title']; ?></title>
        <meta name="description" content="<?php echo $content['description']; ?>">
        <meta property="og:title" content="<?php echo $content['title']; ?>" />
        <meta property="og:description" content="<?php echo $content['description']; ?>" />
        <meta property="og:type" content="article" />
        <meta property="og:url" content="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" />
<?php if($content['extras']['featured']): ?>
        <meta property="og:image" content="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . explode('/', $_SERVER['REQUEST_URI'])[0] . json_decode($content['extras']['featured'])->src; ?>" />
<?php endif; ?>
<<<<<<< HEAD

        <base href="/" />

=======
        
        <base href="/<?php echo FOLDER; ?>" />
        
>>>>>>> back-end
        <script src="<?php echo $minifyScripts; ?>"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo $minifyCSS; ?>">

        <?php echo $scripts; ?>
        <?php echo $CSS; ?>

        <script>
<<<<<<< HEAD

            // Setup main module with HTML5 URLs for SEO
=======
            
            // Setup main module with HTML5 URLs
>>>>>>> back-end
            angular.module('main', [
                'cosmo',
                'ngRoute',
                'ngAnimate',
                'ui.tree',
                'angularFileUpload',
                'ngResource',
                'ngDialog',
                'ngTouch'<?php echo $angularModules; ?>

            ])
<<<<<<< HEAD

            .config(['$routeProvider', '$locationProvider', 'growlProvider', function($routeProvider, $locationProvider, growlProvider) {
=======
            
            .config(['$routeProvider', '$locationProvider', function($routeProvider, $locationProvider) {
>>>>>>> back-end
                // Configure standard URLs
                $routeProvider.
                    when('/admin', { controller: function(ngDialog){ ngDialog.open({ template: 'core/html/login.html', showClose: false, closeByEscape: false, closeByDocument: false }); }, template: '<div></div>' }).
                    when('/reset/:userID/:token', { controller: 'resetModal', template: '<div></div>' }).
                    when('/', { controller: 'urlCtrl', template: '<div ng-include="template"></div>' }).
                    when('/:url', { controller: 'urlCtrl', template: '<div ng-include="template"></div>' });

                // Enable HTML5 urls
                $locationProvider.html5Mode(true).hashPrefix('!');
<<<<<<< HEAD

                // Timeout messages after 5 seconds
                growlProvider.globalTimeToLive(5000);
                // growlProvider.globalEnableHtml(true);
=======
>>>>>>> back-end
            }])

            // Initialize JS variables
<<<<<<< HEAD
            .run(['Users', '$http', '$templateCache', 'REST', '$rootScope', 'growl', 'Page', function(Users, $http, $templateCache, REST, $rootScope, growl, Page) {

                growl.addSuccessMessage('Message', { ttl: 999, classes: 'cosmo-default' });

=======
            .run(['Users', '$http', '$templateCache', 'REST', '$rootScope', 'Page', function(Users, $http, $templateCache, REST, $rootScope, Page) {
                
>>>>>>> back-end
                Users.username = '<?php echo $username; ?>';<?php if($usersID): ?>

                Users.id = <?php echo $usersID; ?>;<?php endif; ?>
<<<<<<< HEAD

                Users.role = '<?php echo $role; ?>';

=======
                
                Users.role = '<?php echo $role; ?>';<?php if($directives): ?>
                
                Page.directives = <?php echo json_encode($directives); ?>;<? endif; ?>
                
                Page.classes = "<?php echo $classes; ?>";
                Page.themePages = <?php echo json_encode($themeJSON->pages); ?>;
                Page.folder = '<?php echo FOLDER; ?>';
                
>>>>>>> back-end
                // If the user has permissions, show the sidebar.
                if(Users.role === 'admin' || Users.role === 'editor' || Users.role === 'contributor' || Users.id){
                    Users.admin = true;
                    $rootScope.$broadcast('adminLogin');
                }
<<<<<<< HEAD

=======
                
                // Get the user's role number
                switch(Users.role){
                    case 'admin':
                        Users.roleNum = 1;
                        break;
                    case 'editor':
                        Users.roleNum = 2;
                        break;
                    case 'contributor':
                        Users.roleNum = 3;
                        break;
                    default:
                        Users.roleNum = 4;
                        break;
                }
                
>>>>>>> back-end
                // Initialize headers for authorizing API calls
                $http.defaults.headers.common['usersID'] = '<?php echo $usersID; ?>';
                $http.defaults.headers.common['username'] = '<?php echo $username; ?>';
                $http.defaults.headers.common['token'] = '<?php echo $token; ?>';

                // Load template
                REST.settings.get({}, function(data){
                    Page.settings = data;
                    Page.theme = data.theme;
                    $rootScope.$broadcast('settingsGet', data);
                });

                // Load menus
                REST.menus.query({}, function(data){
                    Page.menus = data;
                    $rootScope.$broadcast('menusGet', data);
                });

                // Cache all template pages
                angular.forEach(Page.themePages, function(page){
                    $templateCache.put('themes/<?php echo $theme; ?>/'+page);
                });

            }]);
<<<<<<< HEAD

=======
>>>>>>> back-end
        </script>
    </head>
    <body>
        <div ng-if="admin">
            <div ng-include="'core/html/admin-panel.html'"></div>
            <div cs-wysiwyg></div>
        </div>
<<<<<<< HEAD

        <div ng-view class="at-view-fade-in at-view-fade-out cosmo-theme"><?php echo $content['body']; ?></div>
        <div notification></div>
=======
        <div ng-view class="cosmo-theme"><?php echo $content['body']; ?></div>
        <div cs-notification></div>
>>>>>>> back-end
    </body>
</html>
