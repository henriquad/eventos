var LOGIN_LOCAL_KEY = "eventos.login.persistido";
var LOGIN_SESSION_KEY = "eventos.login.sessao";

function limparLoginSalvo() {
  window.sessionStorage.removeItem(LOGIN_SESSION_KEY);
  window.localStorage.removeItem(LOGIN_LOCAL_KEY);
}

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

function excluirRegistrosRelacionados(apelido, senha) {
  var params = new URLSearchParams();
  params.append("username", apelido);
  params.append("password", senha);

  return fetch("PHP/excluir_credenciais.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
    },
    body: params.toString(),
  }).then(function (response) {
    return response
      .json()
      .catch(function () {
        return { success: false, message: "Resposta invalida do servidor." };
      })
      .then(function (payload) {
        if (!response.ok || !payload.success) {
          throw new Error(
            payload && payload.message
              ? payload.message
              : "Nao foi possivel excluir os registros relacionados.",
          );
        }

        return payload;
      });
  });
}

function tratarSolicitacaoLimpezaLogin() {
  var params = new URLSearchParams(window.location.search);
  var deveLimpar = params.get("limparLogin") === "1";

  if (!deveLimpar) {
    return;
  }

  limparLoginSalvo();
  if (window.history && window.history.replaceState) {
    params.delete("limparLogin");
    var query = params.toString();
    var novaUrl = window.location.pathname + (query ? "?" + query : "");
    window.history.replaceState(null, "", novaUrl);
  }
}

function inicializarPersistenciaLogin() {
  tratarSolicitacaoLimpezaLogin();

  var form = document.getElementById("loginForm");
  var inputApelido = document.getElementById("username");
  var inputSenha = document.getElementById("password");
  var lembrarCheckbox = document.getElementById("rememberLogin");
  var botaoEsquecer = document.getElementById("forgetLogin");
  var statusEl = document.getElementById("loginStorageStatus");

  if (!form || !inputApelido || !inputSenha || !lembrarCheckbox) {
    return;
  }

  function definirStatus(texto, tipo) {
    if (!statusEl) {
      return;
    }

    statusEl.textContent = texto;
    statusEl.classList.remove("is-success", "is-info");
    if (tipo) {
      statusEl.classList.add(tipo);
    }
  }

  var salvo = lerLoginSalvo();
  if (salvo) {
    inputApelido.value = String(salvo.apelido || "");
    inputSenha.value = String(salvo.senha || "");
    lembrarCheckbox.checked = Boolean(salvo.lembrarNoDispositivo);
  }

  if (botaoEsquecer) {
    botaoEsquecer.addEventListener("click", function () {
      var apelidoDigitado = inputApelido.value.trim();
      var senhaDigitada = inputSenha.value.trim();

      if (!apelidoDigitado || !senhaDigitada) {
        limparLoginSalvo();
        inputApelido.value = "";
        inputSenha.value = "";
        lembrarCheckbox.checked = false;
        definirStatus(
          "Apelido e senha removidos deste dispositivo.",
          "is-success",
        );
        inputApelido.focus();
        return;
      }

      var confirmarExclusao = window.confirm(
        "Excluir tambem todos os registros vinculados a este apelido e senha?",
      );

      if (!confirmarExclusao) {
        return;
      }

      botaoEsquecer.disabled = true;
      definirStatus("Excluindo registros relacionados...", "is-info");

      excluirRegistrosRelacionados(apelidoDigitado, senhaDigitada)
        .then(function () {
          limparLoginSalvo();
          inputApelido.value = "";
          inputSenha.value = "";
          lembrarCheckbox.checked = false;
          definirStatus(
            "Apelido e senha removidos. Registros relacionados excluidos.",
            "is-success",
          );
          inputApelido.focus();
        })
        .catch(function (error) {
          definirStatus(error.message, "is-info");
        })
        .finally(function () {
          botaoEsquecer.disabled = false;
        });
    });
  }

  form.addEventListener("submit", function (event) {
    definirStatus("", "");

    var apelidoDigitado = inputApelido.value.trim();
    var senhaDigitada = inputSenha.value.trim();

    if (!apelidoDigitado || !senhaDigitada) {
      event.preventDefault();
      definirStatus("Apelido e senha são obrigatórios para entrar.", "is-info");
      if (!apelidoDigitado) {
        inputApelido.focus();
      } else {
        inputSenha.focus();
      }
      return;
    }

    salvarLogin(
      {
        apelido: apelidoDigitado,
        senha: inputSenha.value,
        lembrarNoDispositivo: lembrarCheckbox.checked,
      },
      lembrarCheckbox.checked,
    );

    if (!lembrarCheckbox.checked) {
      definirStatus("Dados salvos somente nesta sessão.", "is-info");
    }
  });
}

inicializarPersistenciaLogin();
