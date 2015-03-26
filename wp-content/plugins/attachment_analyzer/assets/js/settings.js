/**
 * Created by Bobby on 3/15/2015.
 */
function ud_find_text(self) {
    var children = self.parentNode.getElementsByTagName('input');
    for (var i = 0; i < children.length; i++) {
        if (children[i].getAttribute('type') == 'number') {
            return children[i];
        }
    }
}

function ud_inc(self) {
    var text = ud_find_text(self);
    text.value++;
}

function ud_dec(self) {
    var text = ud_find_text(self);
    if (text.value > 0) text.value--;
}