var STORAGE_KEY = "eventos.periodoReferencia";
var MENU_INIT_FLAG = "__eventosMenuInicializado";

function storageSetItem(chave, valor) {
  try {
    window.localStorage.setItem(chave, valor);
    return true;
  } catch (error) {
    return false;
  }
}

function storageGetItem(chave) {
  try {
    return window.localStorage.getItem(chave);
  } catch (error) {
    return null;
  }
}

function storageRemoveItem(chave) {
  try {
    window.localStorage.removeItem(chave);
    return true;
  } catch (error) {
    return false;
  }
}

function apenasDigitos(valor) {
  return String(valor || "")
    .replace(/\D/g, "")
    .slice(0, 8);
}

function formatarMascaraData(valor) {
  var digitos = apenasDigitos(valor);

  if (digitos.length <= 2) {
    return digitos;
  }

  if (digitos.length <= 4) {
    return digitos.slice(0, 2) + "/" + digitos.slice(2);
  }

  return (
    digitos.slice(0, 2) + "/" + digitos.slice(2, 4) + "/" + digitos.slice(4)
  );
}

function parseDataBr(valor) {
  var texto = String(valor || "").trim();
  if (!texto) {
    return null;
  }

  var matchIso = texto.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  var matchBr = texto.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/);

  var dia;
  var mes;
  var ano;

  if (matchIso) {
    ano = Number(matchIso[1]);
    mes = Number(matchIso[2]);
    dia = Number(matchIso[3]);
  } else if (matchBr) {
    dia = Number(matchBr[1]);
    mes = Number(matchBr[2]);
    ano = Number(matchBr[3]);
  } else {
    return null;
  }

  var data = new Date(ano, mes - 1, dia);

  if (
    data.getFullYear() !== ano ||
    data.getMonth() !== mes - 1 ||
    data.getDate() !== dia
  ) {
    return null;
  }

  data.setHours(0, 0, 0, 0);
  return data;
}

function formatarDataBr(data) {
  var dia = String(data.getDate()).padStart(2, "0");
  var mes = String(data.getMonth() + 1).padStart(2, "0");
  var ano = String(data.getFullYear());
  return dia + "/" + mes + "/" + ano;
}

function diferencaEmDias(inicio, fim) {
  var umDia = 24 * 60 * 60 * 1000;
  return Math.round((fim.getTime() - inicio.getTime()) / umDia) + 1;
}

function parseValorMonetario(valor) {
  var texto = String(valor || "").trim();
  if (!texto) {
    return null;
  }

  texto = texto.replace(/\s+/g, "").replace(/^R\$/i, "");
  texto = texto.replace(/[^0-9,.-]/g, "");

  var negativo = false;
  if (texto.charAt(0) === "-") {
    negativo = true;
    texto = texto.slice(1);
  }

  if (!texto) {
    return null;
  }

  var temVirgula = texto.indexOf(",") !== -1;
  var temPonto = texto.indexOf(".") !== -1;

  if (temVirgula && temPonto) {
    if (texto.lastIndexOf(",") > texto.lastIndexOf(".")) {
      texto = texto.replace(/\./g, "").replace(",", ".");
    } else {
      texto = texto.replace(/,/g, "");
    }
  } else if (temVirgula) {
    var partesVirgula = texto.split(",");
    if (partesVirgula.length > 2) {
      texto = partesVirgula.join("");
    } else if (partesVirgula[1] && partesVirgula[1].length === 3) {
      texto = partesVirgula[0] + partesVirgula[1];
    } else {
      texto = partesVirgula[0] + "." + (partesVirgula[1] || "");
    }
  } else if (temPonto) {
    var partesPonto = texto.split(".");
    if (partesPonto.length > 2) {
      texto = partesPonto.join("");
    } else if (partesPonto[1] && partesPonto[1].length === 3) {
      texto = partesPonto[0] + partesPonto[1];
    }
  }

  if (negativo) {
    texto = "-" + texto;
  }

  if (!/^-?\d+(\.\d+)?$/.test(texto)) {
    return null;
  }

  var numero = Number(texto);
  return Number.isFinite(numero) ? numero : null;
}

function salvarPeriodo(dataInicial, dataFinal, saldoAnterior) {
  var payload = JSON.stringify({
    dataInicial: dataInicial,
    dataFinal: dataFinal,
    saldoAnterior: saldoAnterior,
  });
  storageSetItem(STORAGE_KEY, payload);
}

function carregarPeriodoSalvo() {
  try {
    var bruto = storageGetItem(STORAGE_KEY);
    return bruto ? JSON.parse(bruto) : null;
  } catch (error) {
    return null;
  }
}

function atualizarClasseCampo(input, status) {
  input.classList.remove("is-valid");
  input.classList.remove("is-invalid");

  if (status) {
    input.classList.add(status);
  }
}

function definirStatus(statusEl, tipo, texto) {
  statusEl.textContent = texto;
  statusEl.classList.remove("is-valid");
  statusEl.classList.remove("is-invalid");

  if (tipo) {
    statusEl.classList.add(tipo);
  }
}

function aplicarPeriodo(
  dataInicial,
  dataFinal,
  dataIEl,
  dataFEl,
  statusEl,
  saldoAnteriorEl,
) {
  dataIEl.value = formatarDataBr(dataInicial);
  dataFEl.value = formatarDataBr(dataFinal);
  validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl);
}

function validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl) {
  var valorInicial = dataIEl.value.trim();
  var valorFinal = dataFEl.value.trim();
  var dataInicial = parseDataBr(valorInicial);
  var dataFinal = parseDataBr(valorFinal);
  var saldoAnterior = saldoAnteriorEl ? saldoAnteriorEl.value.trim() : "";

  atualizarClasseCampo(dataIEl, "");
  atualizarClasseCampo(dataFEl, "");

  if (!valorInicial && !valorFinal) {
    definirStatus(
      statusEl,
      "",
      "Informe as duas datas para validar o intervalo.",
    );
    storageRemoveItem(STORAGE_KEY);
    return;
  }

  if (!dataInicial && valorInicial) {
    atualizarClasseCampo(dataIEl, "is-invalid");
    definirStatus(
      statusEl,
      "is-invalid",
      "Data inicial inválida. Use o formato dd/mm/aaaa.",
    );
    return;
  }

  if (!dataFinal && valorFinal) {
    atualizarClasseCampo(dataFEl, "is-invalid");
    definirStatus(
      statusEl,
      "is-invalid",
      "Data final inválida. Use o formato dd/mm/aaaa.",
    );
    return;
  }

  if (!dataInicial || !dataFinal) {
    definirStatus(statusEl, "", "Complete o período com data inicial e final.");
    return;
  }

  if (dataFinal.getTime() < dataInicial.getTime()) {
    atualizarClasseCampo(dataIEl, "is-invalid");
    atualizarClasseCampo(dataFEl, "is-invalid");
    definirStatus(
      statusEl,
      "is-invalid",
      "A data final não pode ser menor que a data inicial.",
    );
    return;
  }

  atualizarClasseCampo(dataIEl, "is-valid");
  atualizarClasseCampo(dataFEl, "is-valid");

  var totalDias = diferencaEmDias(dataInicial, dataFinal);
  salvarPeriodo(valorInicial, valorFinal, saldoAnterior);
  definirStatus(
    statusEl,
    "is-valid",
    "Período válido: " +
      totalDias +
      " dia(s) entre " +
      valorInicial +
      " e " +
      valorFinal +
      ".",
  );
}

function conectarMascara(input, dataIEl, dataFEl, statusEl, saldoAnteriorEl) {
  input.addEventListener("input", function () {
    var posicao = input.selectionStart;
    input.value = formatarMascaraData(input.value);

    if (typeof posicao === "number") {
      input.setSelectionRange(input.value.length, input.value.length);
    }

    validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl);
  });

  input.addEventListener("blur", function () {
    input.value = formatarMascaraData(input.value);
    validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl);
  });
}

function inicializarPeriodo() {
  var dataIEl = document.getElementById("DataI");
  var dataFEl = document.getElementById("DataF");
  var saldoAnteriorEl = document.getElementById("SaldoAnterior");
  var statusEl = document.getElementById("periodoStatus");
  var botoes = document.querySelectorAll(".periodo-btn");

  if (!dataIEl || !dataFEl || !statusEl) {
    return;
  }

  conectarMascara(dataIEl, dataIEl, dataFEl, statusEl, saldoAnteriorEl);
  conectarMascara(dataFEl, dataIEl, dataFEl, statusEl, saldoAnteriorEl);

  var isFreshLogin = /[?&]fresh=1/.test(window.location.search);
  if (isFreshLogin && window.history && window.history.replaceState) {
    var urlLimpa = window.location.pathname + window.location.hash;
    window.history.replaceState(null, "", urlLimpa);
  }

  var salvo = carregarPeriodoSalvo();
  if (salvo && salvo.dataInicial && salvo.dataFinal) {
    dataIEl.value = formatarMascaraData(salvo.dataInicial);
    dataFEl.value = formatarMascaraData(salvo.dataFinal);
    if (saldoAnteriorEl) {
      saldoAnteriorEl.value =
        !isFreshLogin &&
        salvo.saldoAnterior !== undefined &&
        salvo.saldoAnterior !== null &&
        salvo.saldoAnterior !== ""
          ? String(salvo.saldoAnterior)
          : "";
    }
  } else {
    var hoje = new Date();
    var primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    dataIEl.value = formatarDataBr(primeiroDia);
    dataFEl.value = formatarDataBr(hoje);
  }

  validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl);

  if (saldoAnteriorEl) {
    saldoAnteriorEl.addEventListener("input", function () {
      validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl);
    });

    saldoAnteriorEl.addEventListener("blur", function () {
      validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl);
    });
  }

  botoes.forEach(function (botao) {
    botao.addEventListener("click", function () {
      var tipo = botao.getAttribute("data-periodo");
      var hoje = new Date();
      hoje.setHours(0, 0, 0, 0);

      if (tipo === "limpar") {
        dataIEl.value = "";
        dataFEl.value = "";
        if (saldoAnteriorEl) {
          saldoAnteriorEl.value = "";
        }
        validarPeriodo(dataIEl, dataFEl, statusEl, saldoAnteriorEl);
        return;
      }

      if (tipo === "mes-atual") {
        aplicarPeriodo(
          new Date(hoje.getFullYear(), hoje.getMonth(), 1),
          new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0),
          dataIEl,
          dataFEl,
          statusEl,
          saldoAnteriorEl,
        );
        return;
      }

      if (tipo === "ano-atual") {
        aplicarPeriodo(
          new Date(hoje.getFullYear(), 0, 1),
          new Date(hoje.getFullYear(), 11, 31),
          dataIEl,
          dataFEl,
          statusEl,
          saldoAnteriorEl,
        );
        return;
      }

      if (tipo === "proximos-30") {
        var finalPeriodo = new Date(hoje);
        finalPeriodo.setDate(finalPeriodo.getDate() + 29);
        aplicarPeriodo(
          hoje,
          finalPeriodo,
          dataIEl,
          dataFEl,
          statusEl,
          saldoAnteriorEl,
        );
      }
    });
  });
}

function inicializarLinkComPeriodo(selector, base) {
  var link = document.querySelector(selector);
  var dataIEl = document.getElementById("DataI");
  var dataFEl = document.getElementById("DataF");
  var saldoAnteriorEl = document.getElementById("SaldoAnterior");
  var eventoEl = document.getElementById("EventoExtrato");
  var incluirSaldoAnterior =
    base.indexOf("Extrato.php") !== -1 ||
    base.indexOf("ResumoMensal.php") !== -1;
  var incluirEvento = base.indexOf("Extrato.php") !== -1;

  if (!link || !dataIEl || !dataFEl) {
    return;
  }

  function construirUrl() {
    var dataI = (dataIEl.value || "").trim();
    var dataF = (dataFEl.value || "").trim();

    if (parseDataBr(dataI) && parseDataBr(dataF)) {
      var url =
        base +
        "?DataI=" +
        encodeURIComponent(dataI) +
        "&DataF=" +
        encodeURIComponent(dataF);

      if (incluirSaldoAnterior && saldoAnteriorEl) {
        var saldoTexto = String(saldoAnteriorEl.value || "").trim();
        if (saldoTexto && parseValorMonetario(saldoTexto) !== null) {
          url += "&SaldoAnterior=" + encodeURIComponent(saldoTexto);
        }
      }

      if (incluirEvento && eventoEl) {
        var eventoTexto = String(eventoEl.value || "").trim();
        if (eventoTexto) {
          url += "&Evento=" + encodeURIComponent(eventoTexto);
        }
      }

      return url;
    }

    return base;
  }

  function atualizarHref() {
    link.href = construirUrl();
  }

  atualizarHref();

  dataIEl.addEventListener("input", atualizarHref);
  dataFEl.addEventListener("input", atualizarHref);
  dataIEl.addEventListener("blur", atualizarHref);
  dataFEl.addEventListener("blur", atualizarHref);
  if (saldoAnteriorEl) {
    saldoAnteriorEl.addEventListener("input", atualizarHref);
    saldoAnteriorEl.addEventListener("blur", atualizarHref);
  }
  if (eventoEl) {
    eventoEl.addEventListener("input", atualizarHref);
    eventoEl.addEventListener("blur", atualizarHref);
  }

  link.addEventListener("click", function (event) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return;
    }

    event.preventDefault();
    atualizarHref();
    window.location.href = construirUrl();
  });
}

function inicializarSumariosExclusivos() {
  var itens = document.querySelectorAll(".ajuda-panel details");
  var itemAbertoInicial = null;

  if (!itens.length) {
    return;
  }

  itens.forEach(function (item) {
    if (item.open) {
      if (!itemAbertoInicial) {
        itemAbertoInicial = item;
      } else {
        item.open = false;
      }
    }
  });

  if (!itemAbertoInicial) {
    itens[0].open = true;
    itemAbertoInicial = itens[0];
  }

  itens.forEach(function (itemAtual) {
    itemAtual.addEventListener("toggle", function () {
      if (!itemAtual.open) {
        return;
      }

      itens.forEach(function (outroItem) {
        if (outroItem !== itemAtual) {
          outroItem.open = false;
        }
      });
    });
  });
}

function inicializarMenuEventos() {
  if (window[MENU_INIT_FLAG]) {
    return;
  }
  window[MENU_INIT_FLAG] = true;

  inicializarPeriodo();
  inicializarLinkComPeriodo(
    'a[href="PHP/Extrato.php"], a[href="./PHP/Extrato.php"], a[href*="Extrato.php"]',
    "PHP/Extrato.php",
  );
  inicializarLinkComPeriodo("#linkResumoMensal", "PHP/ResumoMensal.php");
  inicializarSumariosExclusivos();

  var botaoLimparDadosLocais = document.getElementById("clearLocalData");
  if (botaoLimparDadosLocais) {
    botaoLimparDadosLocais.addEventListener("click", function () {
      var dataIEl = document.getElementById("DataI");
      var dataFEl = document.getElementById("DataF");
      var saldoAnteriorEl = document.getElementById("SaldoAnterior");
      var eventoEl = document.getElementById("EventoExtrato");
      var statusEl = document.getElementById("periodoStatus");

      var confirmou = window.confirm(
        "Deseja realmente limpar os dados locais de período e saldo deste navegador?",
      );
      if (!confirmou) {
        if (statusEl) {
          definirStatus(statusEl, "", "Limpeza cancelada pelo usuário.");
        }
        return;
      }

      storageRemoveItem(STORAGE_KEY);

      if (dataIEl) {
        dataIEl.value = "";
        atualizarClasseCampo(dataIEl, "");
      }

      if (dataFEl) {
        dataFEl.value = "";
        atualizarClasseCampo(dataFEl, "");
      }

      if (saldoAnteriorEl) {
        saldoAnteriorEl.value = "";
      }

      if (eventoEl) {
        eventoEl.value = "";
      }

      if (statusEl) {
        definirStatus(
          statusEl,
          "is-valid",
          "Dados locais de período e saldo foram removidos deste navegador.",
        );
      }
    });
  }

  var linkLogout = document.querySelector(".menu-shell__logout");
  if (linkLogout) {
    linkLogout.addEventListener("click", function (event) {
      event.preventDefault();

      var destinoLogout = linkLogout.getAttribute("href") || "PHP/logout.php";
      window.location.href = destinoLogout;
    });
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", inicializarMenuEventos);
} else {
  inicializarMenuEventos();
}
