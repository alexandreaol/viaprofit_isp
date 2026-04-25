-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 25/04/2026 às 15:47
-- Versão do servidor: 11.8.6-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u308598921_via_profit`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `custos_contrato`
--

CREATE TABLE `custos_contrato` (
  `id` int(11) NOT NULL,
  `numero_contrato` varchar(50) NOT NULL,
  `origem` varchar(50) DEFAULT 'manual',
  `origem_id` int(11) DEFAULT NULL,
  `data_custo` date NOT NULL,
  `tipo` enum('instalacao','equipamento','manutencao','material','deslocamento','rede','outro') NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `custos_contrato`
--

INSERT INTO `custos_contrato` (`id`, `numero_contrato`, `origem`, `origem_id`, `data_custo`, `tipo`, `descricao`, `valor`, `criado_em`) VALUES
(1, 'VNT000006', 'equipamento', 1, '2026-03-28', 'equipamento', 'Equipamento vinculado ao contrato', 80.00, '2026-04-24 20:52:28'),
(2, 'VNT000004', 'equipamento', 2, '2025-01-05', 'equipamento', 'Equipamento vinculado ao contrato', 80.00, '2026-04-24 23:45:58'),
(3, 'VNT000007', 'equipamento', 3, '2025-01-05', 'equipamento', 'Equipamento vinculado ao contrato', 80.00, '2026-04-25 00:50:53'),
(5, 'VNT000007', 'manual', NULL, '2026-04-24', 'manutencao', 'troca de conector', 40.00, '2026-04-25 03:19:01');

-- --------------------------------------------------------

--
-- Estrutura para tabela `custos_gerais_mensais`
--

CREATE TABLE `custos_gerais_mensais` (
  `id` int(11) NOT NULL,
  `referencia` char(7) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `custos_mensais_contrato`
--

CREATE TABLE `custos_mensais_contrato` (
  `id` int(11) NOT NULL,
  `numero_contrato` varchar(50) NOT NULL,
  `tipo` enum('rede_neutra','sistema','boleto','pix','suporte','repasse','outro') NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipamentos`
--

CREATE TABLE `equipamentos` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `serial` varchar(100) DEFAULT NULL,
  `mac` varchar(50) DEFAULT NULL,
  `patrimonio` varchar(50) DEFAULT NULL,
  `valor_compra` decimal(10,2) DEFAULT 0.00,
  `data_compra` date DEFAULT NULL,
  `status` enum('estoque','instalado','retirado','manutencao','perdido','vendido') DEFAULT 'estoque',
  `observacao` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `equipamentos`
--

INSERT INTO `equipamentos` (`id`, `tipo`, `marca`, `modelo`, `serial`, `mac`, `patrimonio`, `valor_compra`, `data_compra`, `status`, `observacao`, `criado_em`) VALUES
(1, 'ONT', 'ZTE', 'F670L', 'ZTEGd2e89d6c', '', '', 80.00, '2026-03-01', 'instalado', 'USADO', '2026-04-24 20:52:28'),
(2, 'ROTEADOR', 'ZTE', 'ZXHN H199A', 'ZTEYH86LCT06350', '', '', 80.00, '2025-01-01', 'instalado', '', '2026-04-24 23:45:57'),
(3, 'ROTEADOR', 'ZTE', 'ZXHN H199A', 'ZTEYH86LCT06350', '', '', 80.00, '2025-01-01', 'instalado', 'USADO', '2026-04-25 00:50:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `equipamentos_instalados`
--

CREATE TABLE `equipamentos_instalados` (
  `id` int(11) NOT NULL,
  `numero_contrato` varchar(50) NOT NULL,
  `equipamento_id` int(11) NOT NULL,
  `data_instalacao` date NOT NULL,
  `valor_usado_no_calculo` decimal(10,2) DEFAULT 0.00,
  `custo_instalacao` decimal(10,2) DEFAULT 0.00,
  `status` enum('instalado','retirado','substituido','perdido') DEFAULT 'instalado',
  `observacao` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `equipamentos_instalados`
--

INSERT INTO `equipamentos_instalados` (`id`, `numero_contrato`, `equipamento_id`, `data_instalacao`, `valor_usado_no_calculo`, `custo_instalacao`, `status`, `observacao`, `criado_em`) VALUES
(1, 'VNT000006', 1, '2026-03-28', 80.00, 0.00, 'instalado', 'INSTALAÇÃO TECNICO PAGO', '2026-04-24 20:52:28'),
(2, 'VNT000004', 2, '2025-01-05', 80.00, 0.00, 'instalado', 'ROTEADOR MAE', '2026-04-24 23:45:58'),
(3, 'VNT000007', 3, '2025-01-05', 80.00, 0.00, 'instalado', 'INSTALACAO PROPRIA', '2026-04-25 00:50:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `manutencoes_contrato`
--

CREATE TABLE `manutencoes_contrato` (
  `id` int(11) NOT NULL,
  `numero_contrato` varchar(50) NOT NULL,
  `data_manutencao` date NOT NULL,
  `tipo_manutencao` varchar(100) NOT NULL,
  `tecnico` varchar(150) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `custo_tecnico` decimal(10,2) DEFAULT 0.00,
  `custo_material` decimal(10,2) DEFAULT 0.00,
  `valor_cobrado_cliente` decimal(10,2) DEFAULT 0.00,
  `status` enum('aberta','em_andamento','concluida','cancelada') DEFAULT 'concluida',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `custos_contrato`
--
ALTER TABLE `custos_contrato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_custos_contrato_numero` (`numero_contrato`),
  ADD KEY `idx_custos_contrato_data` (`data_custo`);

--
-- Índices de tabela `custos_gerais_mensais`
--
ALTER TABLE `custos_gerais_mensais`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `custos_mensais_contrato`
--
ALTER TABLE `custos_mensais_contrato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_custos_mensais_numero` (`numero_contrato`),
  ADD KEY `idx_custos_mensais_ativo` (`ativo`);

--
-- Índices de tabela `equipamentos`
--
ALTER TABLE `equipamentos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `equipamentos_instalados`
--
ALTER TABLE `equipamentos_instalados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipamentos_instalados_numero` (`numero_contrato`),
  ADD KEY `equipamento_id` (`equipamento_id`);

--
-- Índices de tabela `manutencoes_contrato`
--
ALTER TABLE `manutencoes_contrato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_manutencoes_contrato_numero` (`numero_contrato`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `custos_contrato`
--
ALTER TABLE `custos_contrato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `custos_gerais_mensais`
--
ALTER TABLE `custos_gerais_mensais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `custos_mensais_contrato`
--
ALTER TABLE `custos_mensais_contrato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `equipamentos`
--
ALTER TABLE `equipamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `equipamentos_instalados`
--
ALTER TABLE `equipamentos_instalados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `manutencoes_contrato`
--
ALTER TABLE `manutencoes_contrato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `equipamentos_instalados`
--
ALTER TABLE `equipamentos_instalados`
  ADD CONSTRAINT `equipamentos_instalados_ibfk_1` FOREIGN KEY (`equipamento_id`) REFERENCES `equipamentos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
