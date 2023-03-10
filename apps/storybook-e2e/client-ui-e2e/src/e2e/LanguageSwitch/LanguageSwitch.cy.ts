describe('client-ui: LanguageSwitch component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=languageswitch--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to LanguageSwitch!');
  });
});
