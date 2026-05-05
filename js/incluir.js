﻿// Limpa todos os campos de recorrência ao escolher dataFixa
document.addEventListener("DOMContentLoaded", function () {
  var dataFixa = document.getElementById("dataFixa");
  if (dataFixa) {
    dataFixa.addEventListener("input", function () {
      if (dataFixa.value) {
        // Só limpa o campo diário se ele for 'sim'
        var radioDiarioSim = document.querySelector(
          'input[name="diario"][value="sim"]',
        );
        if (radioDiarioSim && radioDiarioSim.checked) {
          radioDiarioSim.checked = false;
        }

        // Limpa todos os selects de recorrência
        var todosSelects = [
          document.getElementById("diaM"),
          document.getElementById("diaS"),
          document.getElementById("diaU"),
          document.getElementById("UPA"),
          document.getElementById("diaR"),
          document.getElementById("mesR"),
          document.getElementById("semN"),
          document.getElementById("semD"),
          document.getElementById("semM"),
        ];
        todosSelects.forEach(function (select) {
          if (select) {
            select.selectedIndex = 0;
            var evt = document.createEvent("HTMLEvents");
            evt.initEvent("change", true, false);
            select.dispatchEvent(evt);

            // Força atualização do visual customizado se existir wrapper
            var wrapper = select.nextElementSibling;
            if (
              wrapper &&
              wrapper.classList &&
              wrapper.classList.contains("custom-select")
            ) {
              wrapper.classList.remove("has-selection");
              var label = wrapper.querySelector(".custom-select-label");
              if (label && select.options.length > 0) {
                label.textContent =
                  "\u00A0" + select.options[0].textContent.trim();
              }
              var optionsLi = wrapper.querySelectorAll(".custom-select-option");
              optionsLi.forEach(function (li, idx) {
                li.classList.remove("is-selected");
                if (idx === 0) li.classList.add("is-selected");
              });
            }
          }
        });
      }
    });
  }
});
// Script de validação e feedback para o formulário de inclusão de eventos

function eachNode(nodeList, callback) {
  for (var i = 0; i < nodeList.length; i++) {
    callback(nodeList[i], i);
  }
}

function getQueryParam(paramName) {
  var query = window.location.search || "";
  var regex = new RegExp("(?:\\?|&)" + paramName + "=([^&]*)");
  var match = query.match(regex);
  return match ? decodeURIComponent(match[1].replace(/\+/g, " ")) : null;
}

function dispatchChangeEvent(element) {
  if (typeof Event === "function") {
    element.dispatchEvent(new Event("change", { bubbles: true }));
    return;
  }

  var evt = document.createEvent("HTMLEvents");
  evt.initEvent("change", true, false);
  element.dispatchEvent(evt);
}

document.addEventListener("DOMContentLoaded", function () {
  // Verifica se a página carregou com parâmetro de sucesso
  var salvo = getQueryParam("salvo");
  if (salvo !== null) {
    var id = getQueryParam("id");
    mostrarMensagem("Evento salvo com sucesso! ID: " + id);
    // Limpa o formulário
    document.getElementById("formIncluir").reset();
    // Remove o parâmetro da URL
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  // Verifica se a página carregou com parâmetro de erro vindo do PHP
  var erro = getQueryParam("erro");
  if (erro === "1") {
    var msg = getQueryParam("msg");
    var campos = getQueryParam("campos");
    var textoErro = "Erro ao salvar o evento.";
    if (msg === "campos_obrigatorios") {
      textoErro = "Preencha os campos obrigatórios: " + campos + ".";
    } else if (msg === "grupo_obrigatorio") {
      textoErro = "O campo Grupo é obrigatório.";
    }
    mostrarErro(textoErro);
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  inicializarSelectsCustomizados();
});

function limparRecuoSelect(texto) {
  return String(texto || "").replace(/^[\u00A0\u2003\u2007\u2800\s]+/, "");
}

function comRecuoVisual(texto) {
  return "\u00A0" + limparRecuoSelect(texto);
}

function aplicarRecuoVisualNasOpcoes(select) {
  eachNode(select.options, function (option) {
    var textoLimpo = limparRecuoSelect(option.textContent);
    option.setAttribute("data-label-limpo", textoLimpo);
    option.textContent = comRecuoVisual(textoLimpo);
  });
}

function selectTemEscolhaValida(select) {
  var valor = String(select.value || "");
  return valor !== "" && valor !== "0";
}

function inicializarSelectsCustomizados() {
  var selects = document.querySelectorAll("select");

  eachNode(selects, function (select) {
    if (!select.id || select.getAttribute("data-customizado") === "sim") {
      return;
    }

    aplicarRecuoVisualNasOpcoes(select);

    var wrapper = document.createElement("div");
    wrapper.className = "custom-select";

    var trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "custom-select-trigger";

    var triggerSpacer = document.createElement("span");
    triggerSpacer.className = "custom-select-spacer";

    var triggerLabel = document.createElement("span");
    triggerLabel.className = "custom-select-label";
    trigger.appendChild(triggerSpacer);
    trigger.appendChild(triggerLabel);

    var menu = document.createElement("ul");
    menu.className = "custom-select-menu";

    var options = select.options;

    function atualizarEstadoVisualSelecao() {
      if (selectTemEscolhaValida(select)) {
        wrapper.classList.add("has-selection");
      } else {
        wrapper.classList.remove("has-selection");
      }
    }

    eachNode(options, function (option) {
      var item = document.createElement("li");
      item.className = "custom-select-option";

      var itemSpacer = document.createElement("span");
      itemSpacer.className = "custom-select-spacer";
      item.appendChild(itemSpacer);

      var itemLabel = document.createElement("span");
      itemLabel.className = "custom-select-option-label";
      itemLabel.textContent = comRecuoVisual(option.textContent);
      item.appendChild(itemLabel);

      item.setAttribute("data-value", option.value);

      if (option.selected) {
        item.classList.add("is-selected");
        triggerLabel.textContent = comRecuoVisual(option.textContent);
      }

      item.addEventListener("click", function () {
        select.value = option.value;
        triggerLabel.textContent = comRecuoVisual(option.textContent);

        eachNode(menu.querySelectorAll(".custom-select-option"), function (li) {
          li.classList.remove("is-selected");
        });
        item.classList.add("is-selected");

        wrapper.classList.remove("is-open");
        dispatchChangeEvent(select);
      });

      menu.appendChild(item);
    });

    if (!triggerLabel.textContent && options.length > 0) {
      triggerLabel.textContent = comRecuoVisual(options[0].textContent);
    }

    atualizarEstadoVisualSelecao();

    trigger.addEventListener("click", function (event) {
      event.stopPropagation();
      eachNode(
        document.querySelectorAll(".custom-select.is-open"),
        function (openSelect) {
          if (openSelect !== wrapper) {
            openSelect.classList.remove("is-open");
          }
        },
      );
      wrapper.classList.toggle("is-open");
    });

    select.classList.add("native-select-hidden");
    select.setAttribute("data-customizado", "sim");

    select.parentNode.insertBefore(wrapper, select.nextSibling);
    wrapper.appendChild(trigger);
    wrapper.appendChild(menu);

    select.addEventListener("change", function () {
      var optionAtual = select.options[select.selectedIndex];
      if (!optionAtual) {
        return;
      }

      atualizarEstadoVisualSelecao();

      triggerLabel.textContent = comRecuoVisual(optionAtual.textContent);
      eachNode(menu.querySelectorAll(".custom-select-option"), function (li) {
        var valorItem = li.getAttribute("data-value");
        if (valorItem === optionAtual.value) {
          li.classList.add("is-selected");
        } else {
          li.classList.remove("is-selected");
        }
      });
    });
  });
}

function inicializarExclusividadePrimeiroFieldset() {
  var fieldsets = document.querySelectorAll("form fieldset");
  var primeiroFieldset = null;
  var segundoFieldset = null;
  var terceiroFieldset = null;

  eachNode(fieldsets, function (fieldset) {
    var legend = fieldset.querySelector("legend");
    if (!legend) {
      return;
    }

    var textoLegend = (legend.textContent || "").toLowerCase();
    if (textoLegend.indexOf("escolha apenas uma das 6") !== -1) {
      primeiroFieldset = fieldset;
    }

    if (textoLegend.indexOf("escolha as 2 opções juntas") !== -1) {
      segundoFieldset = fieldset;
    }

    if (textoLegend.indexOf("escolha as 3 opções juntas") !== -1) {
      terceiroFieldset = fieldset;
    }
  });

  if (!primeiroFieldset) {
    return;
  }

  var radiosDiario = primeiroFieldset.querySelectorAll('input[name="diario"]');
  var selectsPrimeiro = primeiroFieldset.querySelectorAll("select");
  var dataF = primeiroFieldset.querySelector("#dataF");
  var selectsSegundo = segundoFieldset
    ? segundoFieldset.querySelectorAll("select")
    : [];
  var selectsTerceiro = terceiroFieldset
    ? terceiroFieldset.querySelectorAll("select")
    : [];

  function limparRadiosDiario() {
    // Só limpa se o valor selecionado for 'sim'
    var radioSim = null;
    eachNode(radiosDiario, function (radio) {
      if (radio.value === "sim" && radio.checked) {
        radioSim = radio;
      }
    });
    if (radioSim) {
      eachNode(radiosDiario, function (radio) {
        radio.checked = false;
      });
    }
  }

  function limparSelects(selects, excecao) {
    eachNode(selects, function (select) {
      if (select === excecao) {
        return;
      }

      if (select.options && select.options.length > 0) {
        select.selectedIndex = 0;
        dispatchChangeEvent(select);
      }
    });
  }

  function limparData(excecao) {
    if (!dataF || dataF === excecao) {
      return;
    }

    dataF.value = "";
    dispatchChangeEvent(dataF);
  }

  function fieldsetTemValorSelecionado(selects) {
    var ativo = false;

    eachNode(selects, function (select) {
      var valor = String(select.value || "");
      if (valor !== "" && valor !== "0") {
        ativo = true;
      }
    });

    return ativo;
  }

  eachNode(radiosDiario, function (radio) {
    radio.addEventListener("change", function () {
      if (!radio.checked) {
        return;
      }
      limparSelects(selectsPrimeiro, null);
      limparData(null);
      limparSelects(selectsSegundo, null);
      limparSelects(selectsTerceiro, null);
    });
  });

  eachNode(selectsPrimeiro, function (selectAtual) {
    selectAtual.addEventListener("change", function () {
      var valor = String(selectAtual.value || "");
      if (valor === "" || valor === "0") {
        return;
      }

      limparRadiosDiario();
      limparSelects(selectsPrimeiro, selectAtual);
      limparData(null);
      limparSelects(selectsSegundo, null);
      limparSelects(selectsTerceiro, null);

      // Limpa o campo dataFixa sempre que qualquer select do primeiro grupo mudar
      var dataFixa = document.getElementById("dataFixa");
      if (dataFixa && dataFixa.value !== "") {
        dataFixa.value = "";
        dispatchChangeEvent(dataFixa);
      }
    });
  });

  // Limpa o campo dataFixa também ao selecionar qualquer radio do primeiro grupo
  eachNode(radiosDiario, function (radio) {
    radio.addEventListener("change", function () {
      if (!radio.checked) {
        return;
      }
      var dataFixa = document.getElementById("dataFixa");
      if (dataFixa && dataFixa.value !== "") {
        dataFixa.value = "";
        dispatchChangeEvent(dataFixa);
      }
    });
  });

  if (dataF) {
    dataF.addEventListener("change", function () {
      if (!dataF.value) {
        return;
      }

      // Limpa todos os campos de recorrência ao escolher dataFixa
      // Limpa todos os radios de diário
      var radiosDiario = document.querySelectorAll('input[name="diario"]');
      eachNode(radiosDiario, function (radio) {
        radio.checked = false;
      });

      // Limpa todos os selects do primeiro grupo
      var selectsPrimeiro = [
        document.getElementById("diaM"),
        document.getElementById("diaS"),
        document.getElementById("diaU"),
        document.getElementById("UPA"),
      ];
      var todosSelects = [
        document.getElementById("diaM"),
        document.getElementById("diaS"),
        document.getElementById("diaU"),
        document.getElementById("UPA"),
        document.getElementById("diaR"),
        document.getElementById("mesR"),
        document.getElementById("semN"),
        document.getElementById("semD"),
        document.getElementById("semM"),
      ];
      todosSelects.forEach(function (select) {
        if (select) {
          select.selectedIndex = 0;
          // Dispara o evento change para garantir atualização do visual customizado
          var evt = document.createEvent("HTMLEvents");
          evt.initEvent("change", true, false);
          select.dispatchEvent(evt);

          // Força atualização do visual customizado se existir wrapper
          var wrapper = select.nextElementSibling;
          if (
            wrapper &&
            wrapper.classList &&
            wrapper.classList.contains("custom-select")
          ) {
            wrapper.classList.remove("has-selection");
            var label = wrapper.querySelector(".custom-select-label");
            if (label && select.options.length > 0) {
              label.textContent = comRecuoVisual(select.options[0].textContent);
            }
            var optionsLi = wrapper.querySelectorAll(".custom-select-option");
            eachNode(optionsLi, function (li) {
              li.classList.remove("is-selected");
            });
            if (optionsLi.length > 0) {
              optionsLi[0].classList.add("is-selected");
            }
          }
        }
      });
    });
  }

  eachNode(selectsSegundo, function (selectAtual) {
    selectAtual.addEventListener("change", function () {
      if (!fieldsetTemValorSelecionado(selectsSegundo)) {
        return;
      }

      limparRadiosDiario();
      limparSelects(selectsPrimeiro, null);
      limparData(null);
      limparSelects(selectsTerceiro, null);

      // Limpa o campo dataFixa ao selecionar qualquer campo do segundo grupo
      var dataFixa = document.getElementById("dataFixa");
      if (dataFixa && dataFixa.value !== "") {
        dataFixa.value = "";
        dispatchChangeEvent(dataFixa);
      }
    });
  });

  eachNode(selectsTerceiro, function (selectAtual) {
    selectAtual.addEventListener("change", function () {
      if (!fieldsetTemValorSelecionado(selectsTerceiro)) {
        return;
      }

      limparRadiosDiario();
      limparSelects(selectsPrimeiro, null);
      limparData(null);
      limparSelects(selectsSegundo, null);

      // Limpa o campo dataFixa ao selecionar qualquer campo do terceiro grupo
      var dataFixa = document.getElementById("dataFixa");
      if (dataFixa && dataFixa.value !== "") {
        dataFixa.value = "";
        dispatchChangeEvent(dataFixa);
      }
    });
  });
}

document.addEventListener("click", function () {
  eachNode(
    document.querySelectorAll(".custom-select.is-open"),
    function (openSelect) {
      openSelect.classList.remove("is-open");
    },
  );
});

document.addEventListener("keydown", function (event) {
  var key = event.key || event.keyCode;
  if (key === "Escape" || key === 27) {
    eachNode(
      document.querySelectorAll(".custom-select.is-open"),
      function (openSelect) {
        openSelect.classList.remove("is-open");
      },
    );
  }
});

document.addEventListener("DOMContentLoaded", function () {
  inicializarExclusividadePrimeiroFieldset();
});

function validarFormulario(e) {
  var nomeEl = document.getElementById("evento");
  var grupoEl = document.getElementById("grupo");
  var valorEEl = document.getElementById("valorE");
  var nome = nomeEl ? nomeEl.value.trim() : "";
  var grupo = grupoEl ? grupoEl.value.trim() : "";
  var valorE = valorEEl ? valorEEl.value.trim() : "";

  // Limpa mensagens de erro anteriores antes de validar novamente
  var container = document.getElementById("area-mensagens");
  if (container) container.innerHTML = "";

  var dc = document.querySelector('input[name="DC"]:checked');
  var prorroga = document.querySelector('input[name="prorroga"]:checked');

  var erros = [];

  if (!nome) {
    erros.push('Campo "Nome do evento" é obrigatório.');
  }

  if (!grupo) {
    erros.push('Campo "Grupo" é obrigatório.');
  }

  if (!valorE) {
    erros.push('Campo "Valor R$" é obrigatório.');
  }

  if (!dc) {
    erros.push("Selecione Débito ou Crédito (DC).");
  }

  if (!prorroga) {
    erros.push("Selecione uma opção de Prorroga.");
  }

  if (erros.length > 0) {
    e.preventDefault();
    mostrarErro(erros.join("<br>"));
    return false;
  }

  return true;
}

function mostrarMensagem(mensagem) {
  alert(mensagem);
}

function mostrarErro(mensagem) {
  var container = document.getElementById("area-mensagens");
  if (!container) {
    container = document.createElement("div");
    container.id = "area-mensagens";
    var form =
      document.getElementById("formIncluir") ||
      document.getElementById("formEditar");
    if (form) {
      form.parentNode.insertBefore(container, form);
    } else {
      document.body.prepend(container);
    }
  }
  container.innerHTML =
    '<p class="periodo-status is-invalid">' + mensagem + "</p>";
  container.scrollIntoView({ behavior: "smooth", block: "center" });
}

// Função para formatar data ao digitar
function formatarDataInput(input) {
  var valor = input.value.replace(/\D/g, "");
  if (valor.length >= 2) {
    valor = valor.substring(0, 2) + "/" + valor.substring(2);
  }
  if (valor.length >= 5) {
    valor = valor.substring(0, 5) + "/" + valor.substring(5, 9);
  }
  input.value = valor;
}

// Aplica o formatador aos campos de data
document.addEventListener("DOMContentLoaded", function () {
  var dataInputs = document.querySelectorAll(
    'input[type="text"][name="DataM"], input[type="text"][name="dataFixa"]',
  );
  eachNode(dataInputs, function (input) {
    input.addEventListener("input", function () {
      formatarDataInput(this);
    });
  });
});
