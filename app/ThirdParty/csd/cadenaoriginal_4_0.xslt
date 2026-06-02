<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:cfdi="http://www.sat.gob.mx/cfd/4">
  <xsl:output method="text" version="1.0" encoding="UTF-8" indent="no"/>
  <xsl:template match="/">
    <xsl:apply-templates select="cfdi:Comprobante"/>
  </xsl:template>
  <xsl:template match="cfdi:Comprobante">
    <xsl:text>|</xsl:text>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Version"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Serie"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Folio"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Fecha"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@FormaPago"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@NoCertificado"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@CondicionesDePago"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@SubTotal"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Descuento"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Moneda"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@TipoCambio"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Total"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@TipoDeComprobante"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Exportacion"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@MetodoPago"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@LugarExpedicion"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Confirmacion"/></xsl:call-template>
    <xsl:apply-templates select="./cfdi:InformacionGlobal"/>
    <xsl:apply-templates select="./cfdi:CfdiRelacionados"/>
    <xsl:apply-templates select="./cfdi:Emisor"/>
    <xsl:apply-templates select="./cfdi:Receptor"/>
    <xsl:apply-templates select="./cfdi:Conceptos"/>
    <xsl:apply-templates select="./cfdi:Impuestos"/>
    <xsl:text>||</xsl:text>
  </xsl:template>
  <xsl:template match="cfdi:InformacionGlobal">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Periodicidad"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Meses"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Año"/></xsl:call-template>
  </xsl:template>
  <xsl:template match="cfdi:CfdiRelacionados">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@TipoRelacion"/></xsl:call-template>
    <xsl:apply-templates select="./cfdi:CfdiRelacionado"/>
  </xsl:template>
  <xsl:template match="cfdi:CfdiRelacionado">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@UUID"/></xsl:call-template>
  </xsl:template>
  <xsl:template match="cfdi:Emisor">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Rfc"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Nombre"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@RegimenFiscal"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@FacAtrAdquirente"/></xsl:call-template>
  </xsl:template>
  <xsl:template match="cfdi:Receptor">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Rfc"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Nombre"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@DomicilioFiscalReceptor"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@ResidenciaFiscal"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@NumRegIdTrib"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@RegimenFiscalReceptor"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@UsoCFDI"/></xsl:call-template>
  </xsl:template>
  <xsl:template match="cfdi:Conceptos">
    <xsl:apply-templates select="./cfdi:Concepto"/>
  </xsl:template>
  <xsl:template match="cfdi:Concepto">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@ClaveProdServ"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@NoIdentificacion"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Cantidad"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@ClaveUnidad"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Unidad"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Descripcion"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@ValorUnitario"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Importe"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Descuento"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@ObjetoImp"/></xsl:call-template>
    <xsl:apply-templates select="./cfdi:Impuestos"/>
  </xsl:template>
  <xsl:template match="cfdi:Concepto/cfdi:Impuestos">
    <xsl:apply-templates select="./cfdi:Retenciones/cfdi:Retencion"/>
    <xsl:apply-templates select="./cfdi:Traslados/cfdi:Traslado"/>
  </xsl:template>
  <xsl:template match="cfdi:Concepto/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Base"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Impuesto"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@TipoFactor"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@TasaOCuota"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Importe"/></xsl:call-template>
  </xsl:template>
  <xsl:template match="cfdi:Concepto/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Base"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Impuesto"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@TipoFactor"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@TasaOCuota"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Importe"/></xsl:call-template>
  </xsl:template>
  <xsl:template match="cfdi:Comprobante/cfdi:Impuestos">
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@TotalImpuestosRetenidos"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@TotalImpuestosTrasladados"/></xsl:call-template>
    <xsl:apply-templates select="./cfdi:Retenciones/cfdi:Retencion"/>
    <xsl:apply-templates select="./cfdi:Traslados/cfdi:Traslado"/>
  </xsl:template>
  <xsl:template match="cfdi:Comprobante/cfdi:Impuestos/cfdi:Retenciones/cfdi:Retencion">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Impuesto"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Importe"/></xsl:call-template>
  </xsl:template>
  <xsl:template match="cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado">
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Base"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@Impuesto"/></xsl:call-template>
    <xsl:call-template name="Requerido"><xsl:with-param name="valor" select="./@TipoFactor"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@TasaOCuota"/></xsl:call-template>
    <xsl:call-template name="Opcional"><xsl:with-param name="valor" select="./@Importe"/></xsl:call-template>
  </xsl:template>
  <xsl:template name="Requerido">
    <xsl:param name="valor"/>
    <xsl:value-of select="concat('|', normalize-space($valor))"/>
  </xsl:template>
  <xsl:template name="Opcional">
    <xsl:param name="valor"/>
    <xsl:if test="normalize-space($valor) != ''">
      <xsl:value-of select="concat('|', normalize-space($valor))"/>
    </xsl:if>
  </xsl:template>
</xsl:stylesheet>
