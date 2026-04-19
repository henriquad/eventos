var LOGIN_LOCAL_KEY = "eventos.login.persistido";
var LOGIN_SESSION_KEY = "eventos.login.sessao";

function lerLoginSalvo() {
  try {
    var sessao = window.sessionStorage.getItem(LOGIN_SESSION_KEY);
    if (sessao) {
      return JSON.parse(sessao);
    }

    var local = window.localStorage.getItem(LOGIN_LOCAL_KEY);
    if (local) {
      return JSON.parse(local);
    }
  } catch (error) {
    return null;
  }

  return null;
}

function salvarLogin(login, lembrarNoDispositivo) {
  var payload = JSON.stringify(login);

  window.sessionStorage.setItem(LOGIN_SESSION_KEY, payload);

  if (lembrarNoDispositivo) {
    window.localStorage.setItem(LOGIN_LOCAL_KEY, payload);
  } else {
    window.localStorage.removeItem(LOGIN_LOCAL_KEY);
  }
}

function inicializarPersistenciaLogin() {
  var form = document.getElementById("loginForm");
  var inputApelido = document.getElementById("username");
  var inputSenha = document.getElementById("password");
  var lembrarCheckbox = document.getElementById("rememberLogin");

  if (!form || !inputApelido || !inputSenha || !lembrarCheckbox) {
    return;
  }

  var salvo = lerLoginSalvo();
  if (salvo) {
    inputApelido.value = String(salvo.apelido || "");
    inputSenha.value = String(salvo.senha || "");
    lembrarCheckbox.checked = Boolean(salvo.lembrarNoDispositivo);
  }

  form.addEventListener("submit", function () {
    salvarLogin(
      {
        apelido: inputApelido.value.trim(),
        senha: inputSenha.value,
        lembrarNoDispositivo: lembrarCheckbox.checked,
      },
      lembrarCheckbox.checked,
    );
  });
}

inicializarPersistenciaLogin();
