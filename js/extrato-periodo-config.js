(function (global) {
  var TIPO_PADRAO = "mes-atual";

  if (typeof global.EVENTOS_PERIODO_PADRAO_EXTRATO !== "string") {
    global.EVENTOS_PERIODO_PADRAO_EXTRATO = TIPO_PADRAO;
  }

  global.eventosObterPeriodoPadraoExtrato = function (referencia) {
    var base =
      referencia instanceof Date ? new Date(referencia.getTime()) : new Date();
    base.setHours(0, 0, 0, 0);

    var tipo = String(
      global.EVENTOS_PERIODO_PADRAO_EXTRATO || TIPO_PADRAO,
    ).toLowerCase();
    var inicio;
    var fim;

    if (tipo === "ano-atual") {
      inicio = new Date(base.getFullYear(), 0, 1);
      fim = new Date(base.getFullYear(), 11, 31);
    } else if (tipo === "proximos-30") {
      inicio = new Date(base);
      fim = new Date(base);
      fim.setDate(fim.getDate() + 29);
    } else {
      inicio = new Date(base.getFullYear(), base.getMonth(), 1);
      fim = new Date(base.getFullYear(), base.getMonth() + 1, 0);
    }

    return {
      tipo: tipo,
      inicio: inicio,
      fim: fim,
    };
  };
})(window);
