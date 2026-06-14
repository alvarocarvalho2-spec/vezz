-- ==========================================
-- BANCO DE DADOS VEZZ (PostgreSQL)
-- Conversão do script MySQL para PostgreSQL
-- ==========================================

-- TABELA PACIENTE
CREATE TABLE tb_paciente (
    id_paciente INTEGER GENERATED ALWAYS AS IDENTITY,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefone VARCHAR(15) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    PRIMARY KEY (id_paciente),
    UNIQUE (cpf),
    UNIQUE (email)
);

-- TABELA CLINICA
CREATE TABLE tb_clinica (
    id_clinica INTEGER GENERATED ALWAYS AS IDENTITY,
    nome VARCHAR(100) NOT NULL,
    cnpj VARCHAR(18) NOT NULL,
    telefone VARCHAR(15) NOT NULL,
    descricao TEXT,
    hora_inicio TIME NOT NULL DEFAULT '08:00',
    hora_fim TIME NOT NULL DEFAULT '18:00',
    dias_atendimento TEXT NOT NULL DEFAULT '1,2,3,4,5', -- dias da semana: 1=Seg .. 7=Dom, padrão seg-sex
    PRIMARY KEY (id_clinica),
    UNIQUE (cnpj)
);

-- TABELA ENDERECO (1:1 com clinica)
CREATE TABLE tb_endereco (
    id_endereco INTEGER GENERATED ALWAYS AS IDENTITY,
    rua VARCHAR(100) NOT NULL,
    numero VARCHAR(10) NOT NULL,
    bairro VARCHAR(100) NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    cep VARCHAR(10) NOT NULL,
    id_clinica INTEGER NOT NULL,
    PRIMARY KEY (id_endereco),
    UNIQUE (id_clinica),
    CONSTRAINT fk_endereco_clinica FOREIGN KEY (id_clinica)
        REFERENCES tb_clinica(id_clinica)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- TABELA GESTOR (N:1 com clinica)
CREATE TABLE tb_gestor (
    id_gestor INTEGER GENERATED ALWAYS AS IDENTITY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    cargo VARCHAR(50) NOT NULL,
    id_clinica INTEGER NOT NULL,
    PRIMARY KEY (id_gestor),
    UNIQUE (email),
    CONSTRAINT fk_gestor_clinica FOREIGN KEY (id_clinica)
        REFERENCES tb_clinica(id_clinica)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- TABELA CONSULTA (N:1 com paciente e clinica)
CREATE TABLE tb_consulta (
    id_consulta INTEGER GENERATED ALWAYS AS IDENTITY,
    data_hora TIMESTAMP NOT NULL,
    status VARCHAR(30) NOT NULL,
    id_paciente INTEGER NOT NULL,
    id_clinica INTEGER NOT NULL,
    PRIMARY KEY (id_consulta),
    CONSTRAINT fk_consulta_paciente FOREIGN KEY (id_paciente)
        REFERENCES tb_paciente(id_paciente)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_consulta_clinica FOREIGN KEY (id_clinica)
        REFERENCES tb_clinica(id_clinica)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- TABELA FILA_ATENDIMENTO (1:1 com consulta)
CREATE TABLE tb_fila_atendimento (
    id_fila_atendimento INTEGER GENERATED ALWAYS AS IDENTITY,
    posicao INTEGER NOT NULL,
    hora_entrada TIMESTAMP NOT NULL,
    hora_inicio TIMESTAMP,
    hora_fim TIMESTAMP,
    id_consulta INTEGER NOT NULL,
    PRIMARY KEY (id_fila_atendimento),
    UNIQUE (id_consulta),
    CONSTRAINT fk_fila_consulta FOREIGN KEY (id_consulta)
        REFERENCES tb_consulta(id_consulta)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- Índices sugeridos
CREATE INDEX idx_consulta_clinica ON tb_consulta(id_clinica);
CREATE INDEX idx_consulta_paciente ON tb_consulta(id_paciente);
CREATE INDEX idx_fila_consulta ON tb_fila_atendimento(id_consulta);

-- FIM DO SCRIPT
                            