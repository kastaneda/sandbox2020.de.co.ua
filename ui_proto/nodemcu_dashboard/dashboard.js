window.addEventListener('load', function() {

  document.querySelector('.btn-mqtt-toggle').addEventListener('click', function (e) {
    e.preventDefault();
    var b = document.querySelector('body');
    b.classList.toggle('status-mqtt-online');
    b.classList.toggle('status-mqtt-offline');
  });

  document.querySelector('.btn-board01-toggle').addEventListener('click', function (e) {
    e.preventDefault();
    var b = document.querySelector('body');
    b.classList.toggle('status-board01-online');
    b.classList.toggle('status-board01-offline');
  });

  document.addEventListener('keydown', function(e) {
    key = event.keyCode? event.keyCode: event.which? event.which: null

    // key 'M'
    if (key == 77 ) {
      e.preventDefault();
      document.querySelector('.btn-mqtt-toggle').click();
    };

    // key 'B'
    if (key == 66 ) {
      e.preventDefault();
      document.querySelector('.btn-board01-toggle').click();
    };
  });

});
